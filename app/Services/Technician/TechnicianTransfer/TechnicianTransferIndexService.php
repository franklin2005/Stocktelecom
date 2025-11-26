<?php

namespace App\Services\Technician\TechnicianTransfer;

use App\Models\Inventory;
use App\Models\MaterialSerial;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\Request;

class TechnicianTransferIndexService
{
    public function __construct(
        private readonly TechnicianTransferLocationService $locationService,
        private readonly TechnicianTransferCartService $cartService,
    ) {
    }

    public function buildIndexData(Request $request): array
    {
        $technician = $request->user();
        $location = $this->locationService->ensureTechnicianLocation($technician);

        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $location->id)
            ->orderBy('material_id')
            ->get();

        $nonSerializedInventory = $inventory
            ->filter(fn ($item) => $item->material && ! $item->material->is_serialized && (int) $item->quantity > 0)
            ->values();
        $serializedInventory = $inventory
            ->filter(fn ($item) => $item->material && $item->material->is_serialized && (int) $item->quantity > 0)
            ->values();

        $availableSerials = MaterialSerial::query()
            ->with('material')
            ->where('current_location_id', $location->id)
            ->where('status', 'assigned')
            ->orderBy('material_id')
            ->orderBy('serial_number')
            ->get();

        $cartData = $this->cartService->prepareCart($request, $location);
        $cartItems = $cartData['items'];
        $cartSummary = $cartData['summary'];
        $reservedQuantities = $cartData['reserved_quantities'];

        $recipientSearch = trim((string) $request->query('recipient_search', ''));
        $recipientQuery = User::technicians()
            ->where('id', '!=', $technician->id);

        if ($recipientSearch !== '') {
            $recipientQuery->where('name', 'like', '%' . $recipientSearch . '%');
        }

        $recipientTechnicians = $recipientQuery
            ->orderBy('name')
            ->limit(25)
            ->get();

        $pendingTransfers = Transfer::query()
            ->with([
                'items.material',
                'items.serial',
                'fromLocation',
            ])
            ->where('type', 'transfer')
            ->where('to_location_id', $location->id)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();

        $pendingReturns = Transfer::query()
            ->with([
                'items.material',
                'items.serial',
                'fromLocation',
                'toLocation',
            ])
            ->where('type', 'return')
            ->where('from_location_id', $location->id)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();

        $recentTransferHistory = Transfer::query()
            ->with([
                'items.material',
                'items.serial',
                'fromLocation',
                'toLocation',
            ])
            ->where('type', 'transfer')
            ->where('to_location_id', $location->id)
            ->whereIn('status', ['accepted', 'rejected'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $recentReturnHistory = Transfer::query()
            ->with([
                'items.material',
                'items.serial',
                'fromLocation',
                'toLocation',
            ])
            ->where('type', 'return')
            ->where('from_location_id', $location->id)
            ->whereIn('status', ['accepted', 'rejected'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return [
            'pendingTransfers' => $pendingTransfers,
            'pendingReturns' => $pendingReturns,
            'recentTransferHistory' => $recentTransferHistory,
            'recentReturnHistory' => $recentReturnHistory,
            'location' => $location,
            'nonSerializedInventory' => $nonSerializedInventory,
            'serializedInventory' => $serializedInventory,
            'availableSerials' => $availableSerials,
            'cartItems' => $cartItems,
            'cartSummary' => $cartSummary,
            'reservedQuantities' => $reservedQuantities,
            'serialsInCart' => $cartData['serials_in_cart'],
            'recipientTechnicians' => $recipientTechnicians,
            'recipientSearch' => $recipientSearch,
        ];
    }
}
