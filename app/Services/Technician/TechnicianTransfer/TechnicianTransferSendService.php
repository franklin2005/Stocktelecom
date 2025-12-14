<?php

namespace App\Services\Technician\TechnicianTransfer;

use App\Models\Material;
use App\Models\MaterialSerial;
use App\Models\StockLocation;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\StockMovementLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
// servicio para enviar transferencias de tecnicos
class TechnicianTransferSendService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockMovementLogger $movementLogger,
    ) {
    }
    // generar numero unico de transferencia
    public function generateTransferNumber(): string
    {   // generar numero de transferencia unico
        do {
            $number = 'TRF-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Transfer::where('order_number', $number)->exists());

        return $number;
    }
    // crear transferencia a partir del carrito
    public function createTransferFromCart(
        User $technician,
        StockLocation $fromLocation,
        User $recipient,
        StockLocation $toLocation,
        array $cartItems,
        array $cartSummary,
        ?string $notes
    ): void {
        $userId = $technician->id;
        // obtener materiales unicos del carrito
        $materialIds = collect($cartItems)
            ->map(fn ($item) => $item['material']->id ?? null)
            ->filter()
            ->unique()
            ->values();
        // cargar modelos de materiales
        $materials = Material::query()
            ->whereIn('id', $materialIds)
            ->get()
            ->keyBy('id');
        // obtener IDs de seriales unicos del carrito
        $serialIds = collect($cartItems)
            ->where('type', 'serial')
            ->map(fn ($item) => $item['serial']->id ?? null)
            ->filter()
            ->unique()
            ->values();
        // ejecutar en transaccion
        DB::transaction(function () use ($fromLocation, $toLocation, $userId, $materials, $serialIds, $cartItems, $notes) {
            $transfer = Transfer::create([
                'order_number' => $this->generateTransferNumber(),
                'type' => 'transfer',
                'from_location_id' => $fromLocation->id,
                'to_location_id' => $toLocation->id,
                'initiator_user_id' => $userId,
                'requires_receiver_accept' => true,
                'status' => 'pending',
                'notes' => $notes,
            ]);
            // obtener modelos de seriales con bloqueo
            $serialModels = $serialIds->isEmpty() ? collect() : MaterialSerial::query()
                ->whereIn('id', $serialIds)
                ->lockForUpdate()   
                ->get()
                ->keyBy('id');
            // procesar cada item del carrito
            foreach ($cartItems as $item) {
                $materialId = $item['material']->id ?? null;
                $material = $materialId !== null ? $materials->get($materialId) : null;
                // validar existencia del material
                if (! $material) {
                    throw new RuntimeException('No se pudo recuperar la informacion del material seleccionado.');
                }
                // procesar segun tipo de item
                if ($item['type'] === 'quantity') {
                    $this->inventoryService->decrease($fromLocation, $material, (int) $item['quantity']);
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
                        $fromLocation,
                        $toLocation,
                        (int) $item['quantity'],
                        'transfer',
                        $transfer->id,
                        $userId
                    );
                    continue;
                }
                // procesar item de tipo serial
                $serialId = $item['serial']->id ?? null;
                $serial = $serialId !== null ? $serialModels->get($serialId) : null;
                // validar existencia y estado del serial
                if (! $serial || $serial->current_location_id !== $fromLocation->id || $serial->status !== 'assigned') {
                    throw new RuntimeException('Alguno de los numeros de serie seleccionados ya no esta disponible.');
                }
                // actualizar estado del serial
                $serial->update([
                    'status' => 'in_transit',
                    'current_location_id' => null,
                ]);
                // disminuir inventario del serial
                $this->inventoryService->decrease($fromLocation, $material, 1);
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
                    $fromLocation,
                    $toLocation,
                    1,
                    'transfer',
                    $transfer->id,
                    $userId
                );
            }
        });
    }
}
