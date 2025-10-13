<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkOrderController extends Controller
{
    /**
     * Listado general de ordenes de trabajo.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $technicianId = (int) $request->query('technician_id');

        $workOrdersQuery = WorkOrder::query()
            ->with('technician')
            ->withCount('items')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($technicianId > 0, fn ($query) => $query->where('technician_id', $technicianId))
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', now()->parse($request->query('from'))->startOfDay()))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', now()->parse($request->query('to'))->endOfDay()));

        $workOrders = $workOrdersQuery
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $technicians = User::technicians()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.work-orders.index', [
            'workOrders' => $workOrders,
            'status' => $status,
            'technicianId' => $technicianId,
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'technicians' => $technicians,
        ]);
    }

    /**
     * Detalle de una orden de trabajo.
     */
    public function show(WorkOrder $workOrder): View
    {
        $workOrder->load(['technician', 'items.material', 'items.serial.material']);

        return view('admin.work-orders.show', [
            'workOrder' => $workOrder,
            'statusOptions' => [
                'open' => 'Abierta',
                'confirmed' => 'Confirmada',
                'cancelled' => 'Cancelada',
            ],
        ]);
    }

    /**
     * Actualiza notas y estado de una orden (solo administradores).
     */
    public function update(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'confirmed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'status.required' => 'Selecciona un estado valido.',
        ]);

        $newStatus = $validated['status'];

        if ($newStatus === 'open' && $workOrder->status !== 'open') {
            $hasAnotherOpen = WorkOrder::query()
                ->where('technician_id', $workOrder->technician_id)
                ->where('status', 'open')
                ->where('id', '!=', $workOrder->id)
                ->exists();

            if ($hasAnotherOpen) {
                return back()->withErrors([
                    'status' => 'El tecnico ya tiene otra orden abierta. Cierrala antes de reabrir esta.',
                ])->withInput();
            }
        }

        $actor = $request->user();

        DB::transaction(function () use ($workOrder, $validated, $newStatus, $actor) {
            $workOrder->update([
                'status' => $newStatus,
                'notes' => $validated['notes'] ?? null,
                'notes_author_type' => $validated['notes'] ? ($actor->role ?? 'admin') : null,
                'notes_author_name' => $validated['notes'] ? $actor->name : null,
            ]);
        });

        return redirect()
            ->route('admin.work-orders.show', $workOrder)
            ->with('status', 'Orden actualizada correctamente.');
    }
}
