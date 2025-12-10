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
    /**
     * Display transfer history for a given technician.
     */
    public function show(Request $request, User $technician): View
    {
        if ($technician->role !== 'technician') {
            abort(404);
        }

        $currentUser = $request->user();

        if ($currentUser->id !== $technician->id && ! in_array($currentUser->role, ['admin', 'super_admin', 'logistics'], true)) {
            abort(403);
        }

        $location = $this->ensureTechnicianLocation($technician);

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

        if ($request->filled('from')) {
            $from = now()->parse($request->query('from'))->startOfDay();
            $transfersQuery->where('created_at', '>=', $from);
        }

        if ($request->filled('to')) {
            $to = now()->parse($request->query('to'))->endOfDay();
            $transfersQuery->where('created_at', '<=', $to);
        }

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

    /**
     * Detail of a specific transfer for a technician.
     */
    public function showTransferDetail(Request $request, User $technician, Transfer $transfer): View
    {
        $location = $this->ensureTechnicianLocation($technician);
        $this->authorizeTechnicianAccess($request->user(), $technician);

        if ($transfer->type !== 'transfer') {
            abort(Response::HTTP_NOT_FOUND);
        }

        // Validar que la transferencia esté relacionada con el técnico (origen o destino o iniciador)
        if ($transfer->initiator_user_id !== $technician->id
            && $transfer->from_location_id !== $location->id
            && $transfer->to_location_id !== $location->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $transfer->load([
            'items.material',
            'items.serial',
            'fromLocation',
            'toLocation',
            'initiator',
        ]);

        $totalUnits = $transfer->items->sum('quantity');

        return view('technician.history-transfer-show', [
            'transfer' => $transfer,
            'technician' => $technician,
            'location' => $location,
            'totalUnits' => $totalUnits,
        ]);
    }

    /**
     * Detail for the authenticated technician (self).
     */
    public function showTransferDetailSelf(Request $request, Transfer $transfer): View
    {
        $technician = $request->user();

        return $this->showTransferDetail($request, $technician, $transfer);
    }

    /**
     * Display return history for a given technician.
     */
    public function showReturns(Request $request, User $technician): View
    {
        if ($technician->role !== 'technician') {
            abort(404);
        }

        $currentUser = $request->user();

        if ($currentUser->id !== $technician->id && ! in_array($currentUser->role, ['admin', 'super_admin', 'logistics'], true)) {
            abort(403);
        }

        $location = $this->ensureTechnicianLocation($technician);

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

        if ($request->filled('from')) {
            $from = now()->parse($request->query('from'))->startOfDay();
            $returnsQuery->where('created_at', '>=', $from);
        }

        if ($request->filled('to')) {
            $to = now()->parse($request->query('to'))->endOfDay();
            $returnsQuery->where('created_at', '<=', $to);
        }

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

    /**
     * Detail of a specific return for a technician.
     */
    public function showReturnDetail(Request $request, User $technician, Transfer $transfer): View
    {
        $location = $this->ensureTechnicianLocation($technician);
        $this->authorizeTechnicianAccess($request->user(), $technician);

        if ($transfer->type !== 'return' || $transfer->from_location_id !== $location->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $transfer->load([
            'items.material',
            'items.serial',
            'fromLocation',
            'toLocation',
            'initiator',
        ]);

        $totalUnits = $transfer->items->sum('quantity');

        return view('technician.history-return-show', [
            'transfer' => $transfer,
            'technician' => $technician,
            'location' => $location,
            'totalUnits' => $totalUnits,
        ]);
    }

    /**
     * Detail of a return for the authenticated technician (self).
     */
    public function showReturnDetailSelf(Request $request, Transfer $transfer): View
    {
        $technician = $request->user();

        return $this->showReturnDetail($request, $technician, $transfer);
    }

    /**
     * Ensure technician stock location exists.
     */
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

    /**
     * Autoriza acceso a datos del técnico.
     */
    protected function authorizeTechnicianAccess(User $currentUser, User $technician): void
    {
        if ($currentUser->id !== $technician->id && ! in_array($currentUser->role, ['admin', 'super_admin', 'logistics'], true)) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }
}
