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
// Servicio para gestionar items en ordenes de trabajo de tecnicos
class TechnicianWorkOrderItemService
{   // inyectar servicios necesarios
    public function __construct(
        private readonly TechnicianWorkOrderLocationService $locationService,
        private readonly InventoryService $inventoryService,
    ) {
    }
    // agregar item de cantidad a la orden de trabajo
    public function addQuantityItem(WorkOrder $workOrder, $technician, int $materialId, int $quantity): void
    {   // validar cantidad positiva
        if ($quantity < 1) {
            throw new RuntimeException('La cantidad debe ser positiva.');
        }// obtener ubicacion del tecnico
        $location = $this->locationService->ensureTechnicianLocation($technician);
        // obtener material no serializado
        $material = Material::query()
            ->whereKey($materialId)
            ->where('is_serialized', false)
            ->first();
        // validar material existente
        if (! $material) {
            throw new RuntimeException('El material seleccionado no es valido.');
        }
        // verificar disponibilidad en inventario
        $inventory = Inventory::query()
            ->where('location_id', $location->id)
            ->where('material_id', $material->id)
            ->first();
        // cantidad disponible en inventario
        $available = $inventory?->quantity ?? 0;
        // cantidad ya agregada en la orden
        $existingQuantity = WorkOrderItem::query()
            ->where('work_order_id', $workOrder->id)
            ->where('material_id', $material->id)
            ->whereNull('material_serial_id')
            ->sum('quantity');
        // validar stock suficiente
        if ($available <= 0 || ($existingQuantity + $quantity) > $available) {
            throw new RuntimeException('No cuentas con stock suficiente para este material.');
        }
        // agregar o actualizar item en la orden de trabajo
        $item = WorkOrderItem::firstOrNew([
            'work_order_id' => $workOrder->id,
            'material_id' => $material->id,
            'material_serial_id' => null,
        ]);
        // actualizar cantidad
        $item->quantity = ($item->quantity ?? 0) + $quantity;
        $item->save();
        // reducir inventario del tecnico
        $this->inventoryService->decrease($location, $material, $quantity);
    }
    // agregar items serializados a la orden de trabajo
    public function addSerialItems(WorkOrder $workOrder, $technician, array $serialIds): void
    {   // procesar IDs de seriales unicos
        $serialIds = collect($serialIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        // validar que se selecciono al menos un serial
        if ($serialIds->isEmpty()) {
            throw new RuntimeException('Selecciona al menos un numero de serie.');
        }
        // obtener ubicacion del tecnico
        $location = $this->locationService->ensureTechnicianLocation($technician);
        // verificar que los seriales no esten ya en la orden
        $existing = WorkOrderItem::query()
            ->where('work_order_id', $workOrder->id)
            ->whereIn('material_serial_id', $serialIds->all())
            ->pluck('material_serial_id');
        // si hay seriales ya en la orden, lanzar error
        if ($existing->isNotEmpty()) {
            throw new RuntimeException('Los numeros de serie ' . $existing->implode(', ') . ' ya forman parte de la orden.');
        }
        // obtener seriales disponibles en la ubicacion del tecnico
        $serials = MaterialSerial::query()
            ->with('material')
            ->whereIn('id', $serialIds->all())
            ->where('current_location_id', $location->id)
            ->where('status', 'assigned')
            ->get();
        // validar que se recuperaron todos los seriales solicitados
        if ($serials->count() !== $serialIds->count()) {
            throw new RuntimeException('Algunos numeros de serie ya no estan disponibles en tu stock.');
        }
        // ejecutar en transaccion
        DB::transaction(function () use ($serials, $workOrder, $location) {
            foreach ($serials as $serial) {
                $lockedSerial = MaterialSerial::query()
                    ->whereKey($serial->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                
                $lockedSerial->loadMissing('material');
                $material = $lockedSerial->material;
                // crear item en la orden de trabajo
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
    // remover item de la orden de trabajo
    public function removeItem(WorkOrder $workOrder, WorkOrderItem $item, $technician): void
    {   // validar que el item pertenece a la orden
        if ($item->work_order_id !== $workOrder->id) {
            abort(403);
        }
        // obtener ubicacion del tecnico
        $location = $this->locationService->ensureTechnicianLocation($technician);
        // ejecutar en transaccion
        DB::transaction(function () use ($item, $location) {
            $item->loadMissing(['material', 'serial.material']);
            // restaurar inventario segun tipo de item
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
                    // aumentar inventario del numero de serie
                    $this->inventoryService->increase($location, $item->material, 1);
                }  
            } elseif ($item->quantity && $item->material) {// restaurar inventario por cantidad (material normal)
                $this->inventoryService->increase($location, $item->material, (int) $item->quantity);
            }
            // eliminar el item de la orden
            $item->delete();
        });
    }
}
