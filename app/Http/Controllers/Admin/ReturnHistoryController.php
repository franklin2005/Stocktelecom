<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnHistoryController extends Controller
{   // Historial de devoluciones de material
    public function index(Request $request): View
    {
        $user = $request->user();
        // solo super_admin y logistica pueden acceder
        if (! in_array($user->role, ['super_admin', 'logistics'], true)) {
            abort(403);
        }
        // obtener filtros de consulta  
        $status = $request->query('status');
        // construir consulta de devoluciones
        $returnsQuery = Transfer::query()
            ->with([
                'items.material',
                'items.serial',
                'fromLocation',
                'toLocation',
                'initiator',
            ])
            ->where('type', 'return');
            // aplicar filtros de fecha si existen
        if ($request->filled('from')) {
            $from = now()->parse($request->query('from'))->startOfDay();
            $returnsQuery->where('created_at', '>=', $from);
        }
        // filtrar hasta fecha
        if ($request->filled('to')) {
            $to = now()->parse($request->query('to'))->endOfDay();
            $returnsQuery->where('created_at', '<=', $to);
        }
        // filtrar por estado si se proporciona
        if ($status && in_array($status, ['pending', 'accepted', 'rejected', 'cancelled'], true)) {
            $returnsQuery->where('status', $status);
        }
        // obtener resultados paginados
        $returns = $returnsQuery
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();
        // retornar vista con datos
        return view('admin.returns-history', [
            'returns' => $returns,
            'selectedStatus' => $status,
        ]);
    }
    // Detalles de una devolucion especifica
    public function show(Request $request, Transfer $transfer): View
    {
        $user = $request->user();
        // solo super_admin y logistica pueden acceder
        if (! in_array($user->role, ['super_admin', 'logistics'], true)) {
            abort(403);
        }
        // verificar que la transferencia sea una devolucion
        if ($transfer->type !== 'return') {
            abort(404);
        }
        // cargar relaciones necesarias
        $transfer->load([
            'items.material',
            'items.serial',
            'fromLocation',
            'toLocation',
            'initiator',
        ]);
        // etiquetas de estado legibles
        $statusLabels = [
            'pending' => 'Pendiente',
            'accepted' => 'Aceptada',
            'rejected' => 'Rechazada',
            'cancelled' => 'Cancelada',
        ];
        // calcular total de unidades devueltas
        $totalUnits = $transfer->items->sum('quantity');
        // retornar vista con datos
        return view('admin.returns-history-show', [
            'returnTransfer' => $transfer,
            'statusLabels' => $statusLabels,
            'totalUnits' => $totalUnits,
        ]);
    }
}
