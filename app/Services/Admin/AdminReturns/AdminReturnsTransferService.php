<?php

namespace App\Services\Admin\AdminReturns;

use App\Models\Material;
use App\Models\MaterialSerial;
use App\Models\StockLocation;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminReturnsTransferService
{   // generar un numero unico para la devolucion
    public function generateTransferNumber(): string
    {
        do {// generar numero con prefijo, fecha y numero aleatorio
            $number = 'TRF-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Transfer::where('order_number', $number)->exists());

        return $number;
    }
    // crear transferencia de devolucion desde tecnico a almacen
    public function createReturnTransfer(
        User $user,
        StockLocation $technicianLocation,
        StockLocation $warehouse,
        array $items,
        Collection $inventory,
        array $pendingReservations
    ): void {
        DB::transaction(function () use ($items, $technicianLocation, $warehouse, $user, $inventory, $pendingReservations) {
            $materialIds = collect($items)->pluck('material_id')->unique()->values();
            $materials = Material::query()->whereIn('id', $materialIds)->get()->keyBy('id');

            $transfer = Transfer::create([
                'order_number' => $this->generateTransferNumber(),
                'type' => 'return',
                'from_location_id' => $technicianLocation->id,
                'to_location_id' => $warehouse->id,
                'initiator_user_id' => $user->id,
                'requires_receiver_accept' => true,
                'status' => 'pending',
                'notes' => null,
            ]);
            // procesar cada item para la devolucion
            foreach ($items as $item) {
                $material = $materials->get($item['material_id']);
                // validar existencia del material
                if (! $material) {
                    throw new RuntimeException('No se encontró información del material seleccionado.');
                }
                // procesar segun tipo de item
                if ($item['type'] === 'quantity') {
                    $inventoryItem = $inventory->firstWhere('material_id', $material->id);
                    $reservedKey = 'quantity-' . $material->id;
                    $alreadyReserved = $pendingReservations['quantities'][$reservedKey] ?? 0;
                    $available = max(($inventoryItem->quantity ?? 0) - $alreadyReserved, 0);
                    // validar disponibilidad
                    if ($item['quantity'] > $available) {
                        throw new RuntimeException('El material ' . ucfirst($material->type) . ' ya no cuenta con la disponibilidad solicitada.');
                    }
                    // crear item de transferencia
                    TransferItem::create([
                        'transfer_id' => $transfer->id,
                        'material_id' => $material->id,
                        'quantity' => $item['quantity'],
                    ]);
                } else {    // tipo serializadp
                    $serial = MaterialSerial::query()
                        ->whereKey($item['serial_id'])
                        ->where('current_location_id', $technicianLocation->id)
                        ->where('status', 'assigned')
                        ->first();
                    // validar disponibilidad del serial
                    if (! $serial) {
                        throw new RuntimeException('Alguno de los n??meros de serie ya no est?? disponible.');
                    }
                    // crear item de transferencia
                    TransferItem::create([
                        'transfer_id' => $transfer->id,
                        'material_id' => $material->id,
                        'material_serial_id' => $serial->id,
                    ]);
                }
            }
        });
    }
}
