<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\MaterialSerial;
use App\Models\StockLocation;
use App\Models\Transfer;
use App\Services\InventoryService;
use App\Services\StockMovementLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReturnsController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockMovementLogger $movementLogger,
    ) {
    }

    public function accept(Request $request, Transfer $transfer): RedirectResponse
    {
        $technician = $request->user();
        $technicianLocation = $this->ensureTechnicianLocation($technician);
        $warehouse = $this->warehouseLocation();

        if ($transfer->type !== 'return' || $transfer->from_location_id !== $technicianLocation->id) {
            abort(403);
        }

        if ($transfer->status !== 'pending') {
            return back()->withErrors(['transfer' => 'La solicitud de devolución ya fue procesada.']);
        }

        try {
            DB::transaction(function () use ($transfer, $technicianLocation, $warehouse, $technician) {
                $transfer->loadMissing(['items.material', 'items.serial']);

                $inventory = Inventory::query()
                    ->with('material')
                    ->where('location_id', $technicianLocation->id)
                    ->get()
                    ->keyBy('material_id');

                foreach ($transfer->items as $item) {
                    $material = $item->material;

                    if (! $material) {
                        throw new RuntimeException('No se encontró la información del material a devolver.');
                    }

                    if ($item->material_serial_id) {
                        $serial = MaterialSerial::query()
                            ->whereKey($item->material_serial_id)
                            ->lockForUpdate()
                            ->first();

                        if (! $serial || $serial->current_location_id !== $technicianLocation->id || $serial->status !== 'assigned') {
                            throw new RuntimeException('Alguno de los números de serie ya no se encuentra disponible en tu inventario.');
                        }

                        $serial->update([
                            'status' => 'available',
                            'current_location_id' => $warehouse->id,
                            'reserved_by_user_id' => null,
                            'reserved_at' => null,
                        ]);

                        $this->inventoryService->decrease($technicianLocation, $material, 1);
                        $this->inventoryService->increase($warehouse, $material, 1);

                        $this->movementLogger->log(
                            'transfer_out',
                            $material,
                            $serial,
                            $technicianLocation,
                            $warehouse,
                            1,
                            'transfer',
                            $transfer->id,
                            $technician->id
                        );

                        continue;
                    }

                    $quantity = (int) ($item->quantity ?? 0);

                    if ($quantity < 1) {
                        continue;
                    }

                    $inventoryItem = $inventory->get($material->id);
                    $available = $inventoryItem?->quantity ?? 0;

                    if ($available < $quantity) {
                        throw new RuntimeException('El material ' . ucfirst($material->type) . ' no cuenta con suficientes unidades para devolver.');
                    }

                    $this->inventoryService->decrease($technicianLocation, $material, $quantity);
                    $this->inventoryService->increase($warehouse, $material, $quantity);

                    $this->movementLogger->log(
                        'transfer_out',
                        $material,
                        null,
                        $technicianLocation,
                        $warehouse,
                        $quantity,
                        'transfer',
                        $transfer->id,
                        $technician->id
                    );
                }

                $transfer->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                ]);
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors(['transfer' => $exception->getMessage()]);
        }

        return back()->with('status', 'Devolución aceptada y stock actualizado.');
    }

    public function reject(Request $request, Transfer $transfer): RedirectResponse
    {
        $technician = $request->user();
        $technicianLocation = $this->ensureTechnicianLocation($technician);

        if ($transfer->type !== 'return' || $transfer->from_location_id !== $technicianLocation->id) {
            abort(403);
        }

        if ($transfer->status !== 'pending') {
            return back()->withErrors(['transfer' => 'La solicitud ya fue procesada.']);
        }

        $transfer->update([
            'status' => 'rejected',
            'rejected_at' => now(),
        ]);

        return back()->with('status', 'Has rechazado la solicitud de devolución.');
    }

    protected function warehouseLocation(): StockLocation
    {
        $location = StockLocation::warehouses()->first();

        if (! $location) {
            throw new RuntimeException('No se encontró la ubicación del almacén principal.');
        }

        return $location;
    }

    protected function ensureTechnicianLocation($technician): StockLocation
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
