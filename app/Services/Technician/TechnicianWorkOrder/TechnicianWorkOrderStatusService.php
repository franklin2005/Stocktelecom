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
// Servicio para gestionar el estado de las ordenes de trabajo de tecnicos
class TechnicianWorkOrderStatusService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockMovementLogger $movementLogger,
    ) {
    }
    // confirmar la orden de trabajo
    public function confirmOrder(WorkOrder $workOrder, $technician, $location): void
    {   // recargar la orden con sus items y materiales
        $workOrder->load(['items.material', 'items.serial.material']);
        // ejecutar en transaccion
        DB::transaction(function () use ($workOrder, $location, $technician) {
            $quantityItems = $workOrder->items
                ->filter(fn (WorkOrderItem $item) => $item->material && $item->material_serial_id === null)
                ->groupBy('material_id');
            // registrar movimientos de consumo para items de cantidad
            foreach ($quantityItems as $materialId => $items) {
                $material = $items->first()->material;
                $totalQuantity = (int) $items->sum('quantity');
                // si la cantidad total es menor que 1, continuar
                if ($totalQuantity < 1) {
                    continue;
                }
                // registrar movimiento de consumo
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
            // procesar items serializados
            $serialItems = $workOrder->items
                ->filter(fn (WorkOrderItem $item) => $item->serial !== null);
            // si hay items serializados, procesarlos
            if ($serialItems->isNotEmpty()) {
                $serialIds = $serialItems->pluck('material_serial_id')->unique()->values()->all();
                // obtener modelos de seriales con bloqueo
                $serialModels = MaterialSerial::query()
                    ->whereIn('id', $serialIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                // validar que todos los seriales fueron encontrados
                if ($serialModels->count() !== count($serialIds)) {
                    throw new RuntimeException('Algunos numeros de serie ya no estan disponibles.');
                }
                // procesar cada item serializado
                foreach ($serialItems as $item) {
                    $serial = $serialModels[$item->material_serial_id];
                    $material = $item->material;
                    // cargar relacion material si no esta cargada
                    $serial->loadMissing('material');
                    // validar que el serial este en la ubicacion del tecnico y en estado correcto
                    if ($serial->current_location_id !== $location->id) {
                        throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya no esta en tu stock.');
                    }
                    // validar estado del serial
                    if (
                        ! in_array($serial->status, ['assigned', 'reserved'], true) ||
                        ($serial->status === 'reserved' && (int) $serial->reserved_by_user_id !== $technician->id)
                    ) {
                        throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya no esta disponible para usar.');
                    }
                    // actualizar estado del serial a instalado
                    $serial->update([
                        'status' => 'installed',
                        'current_location_id' => null,
                        'reserved_by_user_id' => null,
                        'reserved_at' => null,
                    ]);
                    // registrar movimiento de consumo
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
            // actualizar estado de la orden de trabajo a confirmada
            $workOrder->update(['status' => 'confirmed']);
        });
    }
    // cancelar la orden de trabajo
    public function cancelOrder(WorkOrder $workOrder, $technician, $location): void
    {   // ejecutar en transaccion
        DB::transaction(function () use ($workOrder, $location) {
            $workOrder->loadMissing(['items.material', 'items.serial.material']);
            // restaurar inventario para cada item de la orden
            foreach ($workOrder->items as $item) {
                if ($item->material_serial_id && $item->serial) {
                    $serial = MaterialSerial::query()
                        ->whereKey($item->material_serial_id)
                        ->lockForUpdate()
                        ->first();
                    // si el serial existe, actualizar su estado e inventario
                    if ($serial) {
                        $serial->update([
                            'status' => 'assigned',
                            'current_location_id' => $location->id,
                            'reserved_by_user_id' => null,
                            'reserved_at' => null,
                        ]);
                        // aumentar inventario si es de numero de serie
                        if ($item->material) {
                            $this->inventoryService->increase($location, $item->material, 1);
                        }
                    }
                } elseif ($item->quantity && $item->material) {// restaurar inventario por cantidad (material normal)
                    $this->inventoryService->increase($location, $item->material, (int) $item->quantity);
                }
            }
            // actualizar estado de la orden de trabajo a cancelada
            $workOrder->update(['status' => 'cancelled']);
        });
    }
}
