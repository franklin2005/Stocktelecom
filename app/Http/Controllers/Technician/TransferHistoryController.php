<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\StockLocation;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TransferHistoryController extends Controller
{
    // mostrar historial de transferencias para un tecnico dado
    public function show(Request $request, User $technician): View
    {   // validar que el usuario es tecnico
        if ($technician->role !== 'technician') {
            abort(404);
        }
        // usuario actual
        $currentUser = $request->user();
        // abortar si el usuario no tiene permisos
        if ($currentUser->id !== $technician->id && ! in_array($currentUser->role, ['admin', 'super_admin', 'logistics'], true)) {
            abort(403);
        }
        // obtener o crear ubicacion de stock del tecnico
        $location = $this->ensureTechnicianLocation($technician);
        // construir consulta de transferencias relacionadas con el tecnico
        $transfersQuery = Transfer::query()
            ->with([
                'items.material',
                'items.serial',
                'fromLocation',
                'toLocation',
                'initiator',
            ])
            ->where('type', 'transfer')
            ->where(function ($query) use ($technician, $location) {
                $query->where('initiator_user_id', $technician->id)
                    ->orWhere('from_location_id', $location->id)
                    ->orWhere('to_location_id', $location->id);
            });
            // filtrar por rango de fechas si se proporcionan
        if ($request->filled('from')) {
            $from = now()->parse($request->query('from'))->startOfDay();
            $transfersQuery->where('created_at', '>=', $from);
        }
        // filtrar hasta fecha
        if ($request->filled('to')) {
            $to = now()->parse($request->query('to'))->endOfDay();
            $transfersQuery->where('created_at', '<=', $to);
        }
        // obtener resultados paginados
        $transfers = $transfersQuery
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('technician.history-transfers', [
            'technician' => $technician,
            'location' => $location,
            'transfers' => $transfers,
            'currentUser' => $currentUser,
        ]);
    }

   // mostrar detalles de una transferencia especifica para un tecnico dado
    public function showTransferDetail(Request $request, User $technician, Transfer $transfer): View
    {   // obtener o crear ubicacion de stock del tecnico
        $location = $this->ensureTechnicianLocation($technician);
        $this->authorizeTechnicianAccess($request->user(), $technician);
        // validar que la transferencia sea de tipo transferencia
        if ($transfer->type !== 'transfer') {
            abort(Response::HTTP_NOT_FOUND);
        }
        // Validar que la transferencia esté relacionada con el técnico (origen o destino o iniciador)
        if ($transfer->initiator_user_id !== $technician->id
            && $transfer->from_location_id !== $location->id
            && $transfer->to_location_id !== $location->id) {
            abort(Response::HTTP_FORBIDDEN);
        }
        // cargar relaciones necesarias
        $transfer->load([
            'items.material',
            'items.serial',
            'fromLocation',
            'toLocation',
            'initiator',
        ]);
        // calcular total de unidades transferidas
        $totalUnits = $transfer->items->sum('quantity');

        return view('technician.history-transfer-show', [
            'transfer' => $transfer,
            'technician' => $technician,
            'location' => $location,
            'totalUnits' => $totalUnits,
        ]);
    }

    // mostrar detalles de una transferencia para el tecnico autenticado (el mismo)
    public function showTransferDetailSelf(Request $request, Transfer $transfer): View
    {   // tecnico autenticado
        $technician = $request->user();
        // llamar al metodo de detalle de transferencia
        return $this->showTransferDetail($request, $technician, $transfer);
    }

   // asegurar que el tecnico tenga una ubicacion de stock
    public function showReturns(Request $request, User $technician): View
    {   // validar que el usuario es tecnico
        if ($technician->role !== 'technician') {
            abort(404);
        }
        // usuario actual
        $currentUser = $request->user();
        // abortar si el usuario no tiene permisos
        if ($currentUser->id !== $technician->id && ! in_array($currentUser->role, ['admin', 'super_admin', 'logistics'], true)) {
            abort(403);
        }
        // obtener o crear ubicacion de stock del tecnico
        $location = $this->ensureTechnicianLocation($technician);
        // construir consulta de devoluciones relacionadas con el tecnico
        $returnsQuery = Transfer::query()
            ->with([
                'items.material',
                'items.serial',
                'fromLocation',
                'toLocation',
                'initiator',
            ])
            ->where('type', 'return')
            ->where('from_location_id', $location->id);
                // filtrar por rango de fechas si se proporcionan
        if ($request->filled('from')) {
            $from = now()->parse($request->query('from'))->startOfDay();
            $returnsQuery->where('created_at', '>=', $from);
        }
        if ($request->filled('to')) {
            $to = now()->parse($request->query('to'))->endOfDay();
            $returnsQuery->where('created_at', '<=', $to);
        }
        // filtrar por estado si se proporciona
        if ($request->filled('status')) {
            $status = $request->query('status');
            if (in_array($status, ['pending', 'accepted', 'rejected', 'cancelled'], true)) {
                $returnsQuery->where('status', $status);
            }
        }
        $returns = $returnsQuery
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('technician.history-returns', [
            'technician' => $technician,
            'location' => $location,
            'returns' => $returns,
            'currentUser' => $currentUser,
        ]);
    }

   // mostrar detalles de una devolucion especifica para un tecnico dado
    public function showReturnDetail(Request $request, User $technician, Transfer $transfer): View
    {   // obtener o crear ubicacion de stock del tecnico
        $location = $this->ensureTechnicianLocation($technician);
        $this->authorizeTechnicianAccess($request->user(), $technician);
        // validar que la transferencia sea de tipo devolucion
        if ($transfer->type !== 'return' || $transfer->from_location_id !== $location->id) {
            abort(Response::HTTP_NOT_FOUND);
        }
        // cargar relaciones necesarias
        $transfer->load([
            'items.material',
            'items.serial',
            'fromLocation',
            'toLocation',
            'initiator',
        ]);
        // calcular total de unidades devueltas
        $totalUnits = $transfer->items->sum('quantity');

        return view('technician.history-return-show', [
            'transfer' => $transfer,
            'technician' => $technician,
            'location' => $location,
            'totalUnits' => $totalUnits,
        ]);
    }

 // mostrar detalles de una devolucion para el tecnico autenticado (el mismo)
    public function showReturnDetailSelf(Request $request, Transfer $transfer): View
    {
        $technician = $request->user();
        // llamar al metodo de detalle de devolucion
        return $this->showReturnDetail($request, $technician, $transfer);
    }

   // asegurar que el tecnico tenga una ubicacion de stock
    protected function ensureTechnicianLocation(User $technician): StockLocation
    {
        $location = $technician->stockLocation()->first();
        if ($location) {
            return $location;
        }
        return StockLocation::create([
            'location_type' => 'user',
            'ref_id' => $technician->id,
            'name' => 'Stock de ' . $technician->name,
        ]);
    }
    // autorizar acceso del tecnico o roles administrativos
    protected function authorizeTechnicianAccess(User $currentUser, User $technician): void
    {
        if ($currentUser->id !== $technician->id && ! in_array($currentUser->role, ['admin', 'super_admin', 'logistics'], true)) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }
}
