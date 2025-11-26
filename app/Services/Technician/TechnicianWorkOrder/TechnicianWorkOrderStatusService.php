<?php

namespace App\Services\Technician\TechnicianWorkOrder;

use App\Models\MaterialSerial;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\InventoryService;
use App\Services\StockMovementLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TechnicianWorkOrderStatusService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockMovementLogger $movementLogger,
    ) {
    }

    public function confirmOrder(WorkOrder $workOrder, $technician, $location): void
    {
        $workOrder->load(['items.material', 'items.serial.material']);

        if ($workOrder->items->isEmpty()) {
            throw new RuntimeException('Agrega materiales antes de confirmar la orden.');
        }

        DB::transaction(function () use ($workOrder, $location, $technician) {
            $quantityItems = $workOrder->items
                ->filter(fn (WorkOrderItem $item) => $item->material && $item->material_serial_id === null)
                ->groupBy('material_id');

            foreach ($quantityItems as $materialId => $items) {
                $material = $items->first()->material;
                $totalQuantity = (int) $items->sum('quantity');

                if ($totalQuantity < 1) {
                    continue;
                }

                $this->movementLogger->log(
                    'consumption',
                    $material,
                    null,
                    $location,
                    null,
                    $totalQuantity,
                    'work_order',
                    $workOrder->id,
                    $technician->id
                );
            }

            $serialItems = $workOrder->items
                ->filter(fn (WorkOrderItem $item) => $item->serial !== null);

            if ($serialItems->isNotEmpty()) {
                $serialIds = $serialItems->pluck('material_serial_id')->unique()->values()->all();

                /** @var Collection<int, MaterialSerial> $serialModels */
                $serialModels = MaterialSerial::query()
                    ->whereIn('id', $serialIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($serialModels->count() !== count($serialIds)) {
                    throw new RuntimeException('Algunos numeros de serie ya no estan disponibles.');
                }

                foreach ($serialItems as $item) {
                    $serial = $serialModels[$item->material_serial_id];
                    $material = $item->material;

                    $serial->loadMissing('material');

                    if ($serial->current_location_id !== $location->id) {
                        throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya no esta en tu stock.');
                    }

                    if (
                        ! in_array($serial->status, ['assigned', 'reserved'], true) ||
                        ($serial->status === 'reserved' && (int) $serial->reserved_by_user_id !== $technician->id)
                    ) {
                        throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya no esta disponible para usar.');
                    }

                    $serial->update([
                        'status' => 'installed',
                        'current_location_id' => null,
                        'reserved_by_user_id' => null,
                        'reserved_at' => null,
                    ]);

                    $this->movementLogger->log(
                        'consumption',
                        $material,
                        $serial,
                        $location,
                        null,
                        1,
                        'work_order',
                        $workOrder->id,
                        $technician->id
                    );
                }
            }

            $workOrder->update(['status' => 'confirmed']);
        });
    }

    public function cancelOrder(WorkOrder $workOrder, $technician, $location): void
    {
        DB::transaction(function () use ($workOrder, $location) {
            $workOrder->loadMissing(['items.material', 'items.serial.material']);

            foreach ($workOrder->items as $item) {
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

                        if ($item->material) {
                            $this->inventoryService->increase($location, $item->material, 1);
                        }
                    }
                } elseif ($item->quantity && $item->material) {
                    $this->inventoryService->increase($location, $item->material, (int) $item->quantity);
                }
            }

            $workOrder->update(['status' => 'cancelled']);
        });
    }
}
