<?php

namespace App\Services\AdminTransfer;

use App\Models\MaterialSerial;
use App\Models\StockLocation;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Services\InventoryService;
use App\Services\StockMovementLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminTransferCreationService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockMovementLogger $movementLogger,
    ) {
    }

    public function createTransfer(StockLocation $warehouse, StockLocation $technicianLocation, int $userId, Collection $materials, Collection $serialIds, array $cartItems): void
    {
        DB::transaction(function () use ($warehouse, $technicianLocation, $userId, $materials, $serialIds, $cartItems) {
            $transfer = Transfer::create([
                'order_number' => $this->generateTransferNumber(),
                'type' => 'transfer',
                'from_location_id' => $warehouse->id,
                'to_location_id' => $technicianLocation->id,
                'initiator_user_id' => $userId,
                'requires_receiver_accept' => true,
                'status' => 'pending',
                'notes' => null,
            ]);

            $serialModels = $serialIds->isEmpty()
                ? collect()
                : MaterialSerial::query()
                    ->whereIn('id', $serialIds->all())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

            foreach ($cartItems as $item) {
                $material = $materials[$item['material_id']] ?? null;

                if (! $material) {
                    throw new RuntimeException('No se pudo recuperar el material seleccionado.');
                }

                if ($item['type'] === 'quantity') {
                    $this->inventoryService->decrease($warehouse, $material, (int) $item['quantity']);

                    TransferItem::create([
                        'transfer_id' => $transfer->id,
                        'material_id' => $material->id,
                        'quantity' => (int) $item['quantity'],
                    ]);

                    $this->movementLogger->log(
                        'transfer_out',
                        $material,
                        null,
                        $warehouse,
                        $technicianLocation,
                        (int) $item['quantity'],
                        'transfer',
                        $transfer->id,
                        $userId
                    );

                    continue;
                }

                $serial = $serialModels[$item['serial_id']] ?? null;

                if (! $serial || $serial->current_location_id !== $warehouse->id) {
                    throw new RuntimeException('Alguno de los numeros de serie seleccionados ya no esta disponible.');
                }

                if ((int) $serial->reserved_by_user_id !== $userId || $serial->status !== 'reserved') {
                    throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya no esta reservado para tu usuario.');
                }

                $serial->update([
                    'status' => 'assigned',
                    'current_location_id' => null,
                    'reserved_by_user_id' => null,
                    'reserved_at' => null,
                ]);

                $this->inventoryService->decrease($warehouse, $material, 1);

                TransferItem::create([
                    'transfer_id' => $transfer->id,
                    'material_id' => $material->id,
                    'material_serial_id' => $serial->id,
                ]);

                $this->movementLogger->log(
                    'transfer_out',
                    $material,
                    $serial,
                    $warehouse,
                    $technicianLocation,
                    1,
                    'transfer',
                    $transfer->id,
                    $userId
                );
            }
        });
    }

    private function generateTransferNumber(): string
    {
        do {
            $number = 'TRF-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Transfer::where('order_number', $number)->exists());

        return $number;
    }
}
