<?php

namespace App\Services\Admin\Material;

use App\Models\Material;
use App\Models\MaterialSerial;
use App\Models\StockLocation;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Services\InventoryService;
use App\Services\StockMovementLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminMaterialAssignmentService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockMovementLogger $movementLogger,
        private readonly AdminMaterialLocationService $locationService,
    ) {
    }

    public function assignToTechnician(Request $request, Material $material, $technician): RedirectResponse
    {
        $warehouseLocation = $this->locationService->warehouseLocation();
        $technicianLocation = $this->locationService->ensureTechnicianLocation($technician);
        $userId = auth()->id();

        try {
            if ($material->is_serialized) {
                $serialIds = collect($request->input('serial_ids', []))
                    ->map(fn ($id) => (int) $id)
                    ->filter();

                if ($serialIds->isEmpty()) {
                    return back()->withErrors([
                        'serial_ids' => 'Debes seleccionar al menos un numero de serie.',
                    ])->withInput();
                }

                DB::transaction(function () use ($material, $technicianLocation, $warehouseLocation, $serialIds, $userId) {
                    $serials = MaterialSerial::query()
                        ->whereIn('id', $serialIds->all())
                        ->lockForUpdate()
                        ->get();

                    if ($serials->count() !== $serialIds->count()) {
                        throw new RuntimeException('No fue posible recuperar todos los numeros de serie seleccionados.');
                    }

                    foreach ($serials as $serial) {
                        if ($serial->material_id !== $material->id || $serial->status !== 'available' || $serial->current_location_id !== $warehouseLocation->id) {
                            throw new RuntimeException('Alguno de los numeros de serie ya no esta disponible en el almacen.');
                        }
                    }

                    $transfer = Transfer::create([
                        'order_number' => $this->generateTransferNumber(),
                        'type' => 'transfer',
                        'from_location_id' => $warehouseLocation->id,
                        'to_location_id' => $technicianLocation->id,
                        'initiator_user_id' => auth()->id(),
                        'requires_receiver_accept' => true,
                        'status' => 'pending',
                        'notes' => null,
                    ]);

                    foreach ($serials as $serial) {
                        TransferItem::create([
                            'transfer_id' => $transfer->id,
                            'material_id' => $material->id,
                            'material_serial_id' => $serial->id,
                        ]);

                        $serial->update([
                            'status' => 'assigned',
                            'current_location_id' => null,
                        ]);

                        $this->movementLogger->log(
                            'transfer_out',
                            $material,
                            $serial,
                            $warehouseLocation,
                            $technicianLocation,
                            1,
                            'transfer',
                            $transfer->id,
                            $userId
                        );
                    }

                    $this->inventoryService->decrease($warehouseLocation, $material, $serials->count());
                });
            } else {
                $validated = $request->validate([
                    'quantity' => ['required', 'integer', 'min:1'],
                ], [
                    'quantity.required' => 'Debes indicar la cantidad a asignar.',
                    'quantity.min' => 'La cantidad debe ser mayor a cero.',
                ]);

                DB::transaction(function () use ($material, $technicianLocation, $warehouseLocation, $validated, $userId) {
                    $quantity = (int) $validated['quantity'];

                    $this->inventoryService->decrease($warehouseLocation, $material, $quantity);

                    $transfer = Transfer::create([
                        'order_number' => $this->generateTransferNumber(),
                        'type' => 'transfer',
                        'from_location_id' => $warehouseLocation->id,
                        'to_location_id' => $technicianLocation->id,
                        'initiator_user_id' => auth()->id(),
                        'requires_receiver_accept' => true,
                        'status' => 'pending',
                        'notes' => null,
                    ]);

                    TransferItem::create([
                        'transfer_id' => $transfer->id,
                        'material_id' => $material->id,
                        'quantity' => $quantity,
                    ]);

                    $this->movementLogger->log(
                        'transfer_out',
                        $material,
                        null,
                        $warehouseLocation,
                        $technicianLocation,
                        $quantity,
                        'transfer',
                        $transfer->id,
                        $userId
                    );
                });
            }
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'assign_error' => $exception->getMessage(),
            ])->withInput();
        }

        return redirect()
            ->route('admin.materials')
            ->with('status', 'Transferencia creada y pendiente de aceptacion del tecnico.');
    }

    private function generateTransferNumber(): string
    {
        do {
            $number = 'TRF-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Transfer::where('order_number', $number)->exists());

        return $number;
    }
}
