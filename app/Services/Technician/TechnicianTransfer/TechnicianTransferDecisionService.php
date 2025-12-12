<?php

namespace App\Services\Technician\TechnicianTransfer;

use App\Models\MaterialSerial;
use App\Models\Transfer;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\StockMovementLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TechnicianTransferDecisionService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockMovementLogger $movementLogger,
    ) {
    }

    public function acceptTransfer(Transfer $transfer, User $technician): void
    {
        $location = $technician->stockLocation()->firstOrFail();
        $userId = $technician->id;

        DB::transaction(function () use ($transfer, $location, $userId) {
            $transfer = Transfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== 'pending') {
                throw new RuntimeException('La transferencia ya fue procesada.');
            }

            $transfer->loadMissing(['items.material', 'items.serial', 'fromLocation']);

            foreach ($transfer->items as $item) {
                if ($item->material_serial_id) {
                    $serial = MaterialSerial::query()
                        ->whereKey($item->material_serial_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($serial->status !== 'in_transit' && ! ($serial->status === 'assigned' && $serial->current_location_id === null)) {
                        throw new RuntimeException('El serial no esta en transito.');
                    }

                    // Idempotencia: si ya esta en destino, no duplicar inventario.
                    $shouldIncrease = $serial->current_location_id !== $location->id;

                    $serial->update([
                        'status' => 'assigned',
                        'current_location_id' => $location->id,
                    ]);

                    if ($shouldIncrease) {
                        $this->inventoryService->increase($location, $item->material, 1);
                    }

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

                if ($item->quantity) {
                    $this->inventoryService->increase($location, $item->material, (int) $item->quantity);

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

            $transfer->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);
        });
    }

    public function rejectTransfer(Transfer $transfer, User $technician): void
    {
        $location = $technician->stockLocation()->firstOrFail();
        $userId = $technician->id;

        DB::transaction(function () use ($transfer, $userId, $location) {
            $transfer = Transfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== 'pending') {
                throw new RuntimeException('La transferencia ya fue procesada.');
            }

            $transfer->loadMissing(['items.material', 'items.serial', 'fromLocation']);

            $fromLocation = $transfer->fromLocation;

            if (! $fromLocation) {
                throw new RuntimeException('No fue posible determinar la ubicacion de origen.');
            }

            foreach ($transfer->items as $item) {
                if ($item->material_serial_id) {
                    $serial = MaterialSerial::query()
                        ->whereKey($item->material_serial_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($serial->status !== 'in_transit' && ! ($serial->status === 'assigned' && $serial->current_location_id === null)) {
                        throw new RuntimeException('El serial no esta en transito.');
                    }

                    $serial->update([
                        'status' => 'available',
                        'current_location_id' => $fromLocation->id,
                    ]);

                    $this->inventoryService->increase($fromLocation, $item->material, 1);

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

                if ($item->quantity) {
                    $this->inventoryService->increase($fromLocation, $item->material, (int) $item->quantity);

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

            $transfer->update([
                'status' => 'rejected',
                'rejected_at' => now(),
            ]);
        });
    }
}
