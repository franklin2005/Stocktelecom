<?php

namespace App\Services\Admin\AdminTransfer;

use App\Models\MaterialSerial;
use App\Models\StockLocation;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Services\InventoryService;
use App\Services\StockMovementLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
// Servicio para la creacion de transferencias de inventario
class AdminTransferCreationService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockMovementLogger $movementLogger,
    ) {
    }
    // crear transferencia de inventario
    public function createTransfer(StockLocation $warehouse, StockLocation $technicianLocation, int $userId, Collection $materials, Collection $serialIds, array $cartItems): void
    {// ejecutar en transaccion
        DB::transaction(function () use ($warehouse, $technicianLocation, $userId, $materials, $serialIds, $cartItems) {
            $transfer = Transfer::create([// crear registro de transferencia
                'order_number' => $this->generateTransferNumber(),// generar numero unico
                'type' => 'transfer',// tipo de transferencia
                'from_location_id' => $warehouse->id,// ubicacion origen
                'to_location_id' => $technicianLocation->id,// ubicacion destino
                'initiator_user_id' => $userId,// usuario que inicia la transferencia
                'requires_receiver_accept' => true,// requiere aceptacion del receptor
                'status' => 'pending',// estado inicial
                'notes' => null,// notas adicionales
            ]);
            // obtener modelos de seriales con bloqueo
            $serialModels = $serialIds->isEmpty()
                ? collect()
                : MaterialSerial::query()
                    ->whereIn('id', $serialIds->all())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
            // procesar cada item del carrito
            foreach ($cartItems as $item) {
                $material = $materials[$item['material_id']] ?? null;
                // validar existencia del material
                if (! $material) {
                    throw new RuntimeException('No se pudo recuperar el material seleccionado.');
                }
                // procesar segun tipo de item
                if ($item['type'] === 'quantity') {
                    $this->inventoryService->decrease($warehouse, $material, (int) $item['quantity']);
                    // crear registro de item en la transferencia
                    TransferItem::create([
                        'transfer_id' => $transfer->id,
                        'material_id' => $material->id,
                        'quantity' => (int) $item['quantity'],
                    ]);
                    // registrar movimiento de salida de inventario
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
                // procesar item de tipo serial
                $serial = $serialModels[$item['serial_id']] ?? null;
                // validar existencia y disponibilidad del serial
                if (! $serial || $serial->current_location_id !== $warehouse->id) {
                    throw new RuntimeException('Alguno de los numeros de serie seleccionados ya no esta disponible.');
                }
                // validar que el serial este reservado por el usuario
                if ((int) $serial->reserved_by_user_id !== $userId || $serial->status !== 'reserved') {
                    throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya no esta reservado para tu usuario.');
                }
                // actualizar estado del serial
                $serial->update([
                    'status' => 'in_transit',
                    'current_location_id' => null,
                    'reserved_by_user_id' => null,
                    'reserved_at' => null,
                ]);
                // reducir inventario en el almacen
                $this->inventoryService->decrease($warehouse, $material, 1);
                // crear registro de item en la transferencia
                TransferItem::create([
                    'transfer_id' => $transfer->id,
                    'material_id' => $material->id,
                    'material_serial_id' => $serial->id,
                ]);
                // registrar movimiento de salida de inventario
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
    // generar numero unico para la transferencia
    private function generateTransferNumber(): string
    {
        do {// generar numero con formato TRF-YYYYMMDDHHMMSS-XXXX
            $number = 'TRF-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Transfer::where('order_number', $number)->exists()); // repetir si ya existe
        // retornar numero generado
        return $number;
    }
}
