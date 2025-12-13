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
    // asignar material a tecnico
    public function assignToTechnician(Request $request, Material $material, $technician): RedirectResponse
    {   // obtener ubicaciones
        $warehouseLocation = $this->locationService->warehouseLocation();
        $technicianLocation = $this->locationService->ensureTechnicianLocation($technician);
        $userId = auth()->id();// ID del usuario actual
        // intentar crear transferencia
        try {
            if ($material->is_serialized) {
                $serialIds = collect($request->input('serial_ids', []))
                    ->map(fn ($id) => (int) $id)
                    ->filter();
                // validar que se hayan seleccionado seriales
                if ($serialIds->isEmpty()) {
                    return back()->withErrors([
                        'serial_ids' => 'Debes seleccionar al menos un numero de serie.',
                    ])->withInput();
                }
                // crear transferencia para cada serial seleccionado
                DB::transaction(function () use ($material, $technicianLocation, $warehouseLocation, $serialIds, $userId) {
                    $serials = MaterialSerial::query()
                        ->whereIn('id', $serialIds->all())
                        ->lockForUpdate()
                        ->get();
                    // validar que se recuperaron todos los seriales solicitados
                    if ($serials->count() !== $serialIds->count()) {
                        throw new RuntimeException('No fue posible recuperar todos los numeros de serie seleccionados.');
                    }
                    // validar disponibilidad de cada serial
                    foreach ($serials as $serial) {
                        if ($serial->material_id !== $material->id || $serial->status !== 'available' || $serial->current_location_id !== $warehouseLocation->id) {
                            throw new RuntimeException('Alguno de los numeros de serie ya no esta disponible en el almacen.');
                        }
                    }
                    // crear transferencia
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
                    // procesar cada serial
                    foreach ($serials as $serial) {
                        TransferItem::create([
                            'transfer_id' => $transfer->id,
                            'material_id' => $material->id,
                            'material_serial_id' => $serial->id,
                        ]);
                        // actualizar estado
                        $serial->update([
                            'status' => 'in_transit',
                            'current_location_id' => null,
                        ]);
                        // registrar movimiento de salida de inventario
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
                    // disminuir inventario del almacen
                    $this->inventoryService->decrease($warehouseLocation, $material, $serials->count());
                });
            } else {// material por cantidad
                $validated = $request->validate([
                    'quantity' => ['required', 'integer', 'min:1'],
                ], [
                    'quantity.required' => 'Debes indicar la cantidad a asignar.',
                    'quantity.min' => 'La cantidad debe ser mayor a cero.',
                ]);
                // crear transferencia
                DB::transaction(function () use ($material, $technicianLocation, $warehouseLocation, $validated, $userId) {
                    $quantity = (int) $validated['quantity'];
                    // reducir inventario del almacen
                    $this->inventoryService->decrease($warehouseLocation, $material, $quantity);
                    // crear transferencia
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
                    // crear item de transferencia
                    TransferItem::create([
                        'transfer_id' => $transfer->id,
                        'material_id' => $material->id,
                        'quantity' => $quantity,
                    ]);
                    // registrar movimiento de salida de inventario
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
        } catch (RuntimeException $exception) {// manejar errores de asignacion
            return back()->withErrors([
                'assign_error' => $exception->getMessage(),
            ])->withInput();
        }
        // redirigir con exito
        return redirect()
            ->route('admin.materials')
            ->with('status', 'Transferencia creada y pendiente de aceptacion del tecnico.');
    }
    // generar numero unico de transferencia
    private function generateTransferNumber(): string
    {
        do {// generar numero con formato TRF-YYYYMMDDHHMMSS-XXXX
            $number = 'TRF-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Transfer::where('order_number', $number)->exists());// repetir si ya existe
        
        return $number;// retornar numero generado
    }
}
