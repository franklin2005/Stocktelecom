<?php

namespace App\Services\Technician\TechnicianTransfer;

use App\Models\MaterialSerial;
use App\Models\Transfer;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\StockMovementLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;
    // servicio para decisiones sobre transferencias de tecnicos
class TechnicianTransferDecisionService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockMovementLogger $movementLogger,
    ) {
    }
    // aceptar transferencia
    public function acceptTransfer(Transfer $transfer, User $technician): void
    {   // obtener ubicacion del tecnico
        $location = $technician->stockLocation()->firstOrFail();
        $userId = $technician->id;
        // ejecutar en transaccion
        DB::transaction(function () use ($transfer, $location, $userId) {
            $transfer = Transfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            // validar estado de la transferencia
            if ($transfer->status !== 'pending') {
                throw new RuntimeException('La transferencia ya fue procesada.');
            }
            // cargar relaciones necesarias
            $transfer->loadMissing(['items.material', 'items.serial', 'fromLocation']);
            // procesar cada item de la transferencia
            foreach ($transfer->items as $item) {
                if ($item->material_serial_id) {
                    $serial = MaterialSerial::query()
                        ->whereKey($item->material_serial_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    // validar estado del serial
                    if ($serial->status !== 'in_transit' && ! ($serial->status === 'assigned' && $serial->current_location_id === null)) {
                        throw new RuntimeException('El serial no esta en transito.');
                    }
                    // si ya esta en destino, no duplicar inventario.
                    $shouldIncrease = $serial->current_location_id !== $location->id;
                    // actualizar registro del serial
                    $serial->update([
                        'status' => 'assigned',
                        'current_location_id' => $location->id,
                    ]);
                    // actualizar inventario si aplica
                    if ($shouldIncrease) {
                        $this->inventoryService->increase($location, $item->material, 1);
                    }
                    // registrar movimiento de entrada de inventario
                    $this->movementLogger->log(
                        'transfer_in',
                        $item->material,
                        $serial,
                        $transfer->fromLocation,
                        $location,
                        1,
                        'transfer',
                        $transfer->id,
                        $userId
                    );
                    continue;
                }
                // procesar material por cantidad
                if ($item->quantity) {
                    $this->inventoryService->increase($location, $item->material, (int) $item->quantity);
                    // registrar movimiento de entrada de inventario
                    $this->movementLogger->log(
                        'transfer_in',
                        $item->material,
                        null,
                        $transfer->fromLocation,
                        $location,
                        (int) $item->quantity,
                        'transfer',
                        $transfer->id,
                        $userId
                    );
                }
            }
            // actualizar estado de la transferencia
            $transfer->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);
        });
    }
    // rechazar transferencia
    public function rejectTransfer(Transfer $transfer, User $technician): void
    {   // obtener ubicacion del tecnico
        $location = $technician->stockLocation()->firstOrFail();
        $userId = $technician->id;
        // ejecutar en transaccion
        DB::transaction(function () use ($transfer, $userId, $location) {
            $transfer = Transfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            // validar estado de la transferencia, debe estar pendiente
            if ($transfer->status !== 'pending') {
                throw new RuntimeException('La transferencia ya fue procesada.');
            }
            // cargar relaciones necesarias
            $transfer->loadMissing(['items.material', 'items.serial', 'fromLocation']);
            // procesar cada item de la transferencia
            $fromLocation = $transfer->fromLocation;
            // validar existencia de ubicacion de origen
            if (! $fromLocation) {
                throw new RuntimeException('No fue posible determinar la ubicacion de origen.');
            }
            // procesar cada item
            foreach ($transfer->items as $item) {
                if ($item->material_serial_id) {
                    $serial = MaterialSerial::query()
                        ->whereKey($item->material_serial_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    // validar estado del serial
                    if ($serial->status !== 'in_transit' && ! ($serial->status === 'assigned' && $serial->current_location_id === null)) {
                        throw new RuntimeException('El serial no esta en transito.');
                    }
                    // actualizar registro del serial
                    $serial->update([
                        'status' => 'available',
                        'current_location_id' => $fromLocation->id,
                    ]);
                    // devolver inventario al origen
                    $this->inventoryService->increase($fromLocation, $item->material, 1);
                    // registrar movimiento de entrada de inventario
                    $this->movementLogger->log(
                        'transfer_in',
                        $item->material,
                        $serial,
                        null,
                        $fromLocation,
                        1,
                        'transfer',
                        $transfer->id,
                        $userId
                    );
                    continue;
                }
                // procesar material por cantidad
                if ($item->quantity) {
                    $this->inventoryService->increase($fromLocation, $item->material, (int) $item->quantity);
                    // registrar movimiento de entrada de inventario
                    $this->movementLogger->log(
                        'transfer_in',
                        $item->material,
                        null,
                        null,
                        $fromLocation,
                        (int) $item->quantity,
                        'transfer',
                        $transfer->id,
                        $userId
                    );
                }
            }
            // actualizar estado de la transferencia
            $transfer->update([
                'status' => 'rejected',
                'rejected_at' => now(),
            ]);
        });
    }
}
