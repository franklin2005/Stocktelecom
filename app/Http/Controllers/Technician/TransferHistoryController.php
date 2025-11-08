<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\StockLocation;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
}
