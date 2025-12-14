<?php

namespace App\Services\Technician\TechnicianTransfer;

use App\Models\Inventory;
use App\Models\MaterialSerial;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\Request;
// servicio para construir datos de la vista de index de transferencias de tecnicos
class TechnicianTransferIndexService
{
    public function __construct(
        private readonly TechnicianTransferLocationService $locationService,
        private readonly TechnicianTransferCartService $cartService,
    ) {
    }
    // construir datos para la vista de index
    public function buildIndexData(Request $request): array
    {   // obtener tecnico y su ubicacion
        $technician = $request->user();
        $location = $this->locationService->ensureTechnicianLocation($technician);
        // obtener inventario y seriales disponibles en la ubicacion del tecnico
        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $location->id)
            ->orderBy('material_id')
            ->get();
        // separar inventario en serializado y no serializado
        $nonSerializedInventory = $inventory
            ->filter(fn ($item) => $item->material && ! $item->material->is_serialized && (int) $item->quantity > 0)
            ->values();
        $serializedInventory = $inventory
            ->filter(fn ($item) => $item->material && $item->material->is_serialized && (int) $item->quantity > 0)
            ->values();
        // obtener seriales disponibles en la ubicacion del tecnico
        $availableSerials = MaterialSerial::query()
            ->with('material')
            ->where('current_location_id', $location->id)
            ->where('status', 'assigned')
            ->orderBy('material_id')
            ->orderBy('serial_number')
            ->get();
        // preparar el carrito de transferencias
        $cartData = $this->cartService->prepareCart($request, $location);
        $cartItems = $cartData['items'];
        $cartSummary = $cartData['summary'];
        $reservedQuantities = $cartData['reserved_quantities'];
        // buscar tecnicos para posibles destinatarios
        $recipientSearch = trim((string) $request->query('recipient_search', ''));
        $recipientQuery = User::technicians()
            ->where('id', '!=', $technician->id);
        // aplicar filtro de busqueda si aplica
        if ($recipientSearch !== '') {
            $recipientQuery->where('name', 'like', '%' . $recipientSearch . '%');
        }
        // obtener lista de tecnicos destinatarios
        $recipientTechnicians = $recipientQuery
            ->orderBy('name')
            ->limit(25)
            ->get();
        // obtener transferencias y devoluciones pendientes e historico reciente
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
                // devoluciones pendientes
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
                // historico reciente de transferencias
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
                // historico reciente de devoluciones
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
                // retornar datos para la vista
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
