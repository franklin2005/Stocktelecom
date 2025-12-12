<?php

namespace App\Services\Technician\TechnicianWorkOrder;

use App\Models\Inventory;
use App\Models\Material;
use App\Models\MaterialSerial;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TechnicianWorkOrderItemService
{
    public function __construct(
        private readonly TechnicianWorkOrderLocationService $locationService,
        private readonly InventoryService $inventoryService,
    ) {
    }

    public function addQuantityItem(WorkOrder $workOrder, $technician, int $materialId, int $quantity): void
    {
        $location = $this->locationService->ensureTechnicianLocation($technician);

        $material = Material::query()
            ->whereKey($materialId)
            ->where('is_serialized', false)
            ->first();

        if (! $material) {
            throw new RuntimeException('El material seleccionado no es valido.');
        }

        $inventory = Inventory::query()
            ->where('location_id', $location->id)
            ->where('material_id', $material->id)
            ->first();

        $available = $inventory?->quantity ?? 0;

        $existingQuantity = WorkOrderItem::query()
            ->where('work_order_id', $workOrder->id)
            ->where('material_id', $material->id)
            ->whereNull('material_serial_id')
            ->sum('quantity');

        if ($available <= 0 || ($existingQuantity + $quantity) > $available) {
            throw new RuntimeException('No cuentas con stock suficiente para este material.');
        }

        $item = WorkOrderItem::firstOrNew([
            'work_order_id' => $workOrder->id,
            'material_id' => $material->id,
            'material_serial_id' => null,
        ]);

        $item->quantity = ($item->quantity ?? 0) + $quantity;
        $item->save();

        $this->inventoryService->decrease($location, $material, $quantity);
    }

    public function addSerialItems(WorkOrder $workOrder, $technician, array $serialIds): void
    {
        $serialIds = collect($serialIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($serialIds->isEmpty()) {
            throw new RuntimeException('Selecciona al menos un numero de serie.');
        }

        $location = $this->locationService->ensureTechnicianLocation($technician);

        $existing = WorkOrderItem::query()
            ->where('work_order_id', $workOrder->id)
            ->whereIn('material_serial_id', $serialIds->all())
            ->pluck('material_serial_id');

        if ($existing->isNotEmpty()) {
            throw new RuntimeException('Los numeros de serie ' . $existing->implode(', ') . ' ya forman parte de la orden.');
        }

        $serials = MaterialSerial::query()
            ->with('material')
            ->whereIn('id', $serialIds->all())
            ->where('current_location_id', $location->id)
            ->where('status', 'assigned')
            ->get();

        if ($serials->count() !== $serialIds->count()) {
            throw new RuntimeException('Algunos numeros de serie ya no estan disponibles en tu stock.');
        }

        DB::transaction(function () use ($serials, $workOrder, $location) {
            foreach ($serials as $serial) {
                $lockedSerial = MaterialSerial::query()
                    ->whereKey($serial->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedSerial->loadMissing('material');

                $material = $lockedSerial->material;

                WorkOrderItem::create([
                    'work_order_id' => $workOrder->id,
                    'material_id' => $material?->id ?? $lockedSerial->material_id,
                    'material_serial_id' => $lockedSerial->id,
                    'quantity' => null,
                ]);

                if ($material) {
                    // Retiramos del stock del tecnico para que el conteo refleje el uso en la orden abierta.
                    $this->inventoryService->decrease($location, $material, 1);
                }
            }
        });
    }

    public function removeItem(WorkOrder $workOrder, WorkOrderItem $item, $technician): void
    {
        if ($item->work_order_id !== $workOrder->id) {
            abort(403);
        }

        $location = $this->locationService->ensureTechnicianLocation($technician);

        DB::transaction(function () use ($item, $location) {
            $item->loadMissing(['material', 'serial.material']);

            if ($item->material_serial_id && $item->serial) {
                $serial = MaterialSerial::query()
                    ->whereKey($item->material_serial_id)
                    ->lockForUpdate()
                    ->first();

                if ($serial) {
                    $serial->update([
                        'status' => 'assigned',
                        'current_location_id' => $location->id,
                        'reserved_by_user_id' => null,
                        'reserved_at' => null,
                    ]);

                    $this->inventoryService->increase($location, $item->material, 1);
                }
            } elseif ($item->quantity && $item->material) {
                $this->inventoryService->increase($location, $item->material, (int) $item->quantity);
            }

            $item->delete();
        });
    }
}
