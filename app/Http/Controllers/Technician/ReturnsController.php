<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\MaterialSerial;
use App\Models\StockLocation;
use App\Models\Transfer;
use App\Services\InventoryService;
use App\Services\StockMovementLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReturnsController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockMovementLogger $movementLogger,
    ) {
    }
    // aceptar devolucion de transferencia
    public function accept(Request $request, Transfer $transfer): RedirectResponse
    {   // obtener tecnico y ubicaciones
        $technician = $request->user();
        $technicianLocation = $this->ensureTechnicianLocation($technician);
        $warehouse = $this->warehouseLocation();
        // validar que la transferencia sea de devolucion y pertenezca al tecnico
        if ($transfer->type !== 'return' || $transfer->from_location_id !== $technicianLocation->id) {
            abort(403);
        }
        // validar estado pendiente
        if ($transfer->status !== 'pending') {
            return back()->withErrors(['transfer' => 'La solicitud de devolución ya fue procesada.']);
        }
        // procesar devolucion en transaccion
        try {
            DB::transaction(function () use ($transfer, $technicianLocation, $warehouse, $technician) {
                $transfer->loadMissing(['items.material', 'items.serial']);
                // obtener inventario del tecnico
                $inventory = Inventory::query()
                    ->with('material')
                    ->where('location_id', $technicianLocation->id)
                    ->get()
                    ->keyBy('material_id');
                // procesar cada item de la transferencia
                foreach ($transfer->items as $item) {
                    $material = $item->material;
                    // validar existencia del material
                    if (! $material) {
                        throw new RuntimeException('No se encontró la información del material a devolver.');
                    }
                    // procesar segun tipo de item
                    if ($item->material_serial_id) {
                        $serial = MaterialSerial::query()
                            ->whereKey($item->material_serial_id)
                            ->lockForUpdate()
                            ->first();
                        // validar existencia y ubicacion del serial
                        if (! $serial || $serial->current_location_id !== $technicianLocation->id || $serial->status !== 'assigned') {
                            throw new RuntimeException('Alguno de los números de serie ya no se encuentra disponible en tu inventario.');
                        }
                        // actualizar registro del serial
                        $serial->update([
                            'status' => 'available',
                            'current_location_id' => $warehouse->id,
                            'reserved_by_user_id' => null,
                            'reserved_at' => null,
                        ]);
                        // actualizar inventario
                        $this->inventoryService->decrease($technicianLocation, $material, 1);
                        $this->inventoryService->increase($warehouse, $material, 1);
                        // registrar movimiento de stock
                        $this->movementLogger->log(
                            'transfer_out',
                            $material,
                            $serial,
                            $technicianLocation,
                            $warehouse,
                            1,
                            'transfer',
                            $transfer->id,
                            $technician->id
                        );
                        continue;
                    }
                    // procesar item de tipo cantidad
                    $quantity = (int) ($item->quantity ?? 0);
                    // si la cantidad es menor a 1, continuar
                    if ($quantity < 1) {
                        continue;
                    }
                    // validar disponibilidad en inventario del tecnico
                    $inventoryItem = $inventory->get($material->id);
                    $available = $inventoryItem?->quantity ?? 0;
                    // si no hay suficientes unidades, lanzar excepcion
                    if ($available < $quantity) {
                        throw new RuntimeException('El material ' . ucfirst($material->type) . ' no cuenta con suficientes unidades para devolver.');
                    }
                    // actualizar inventario
                    $this->inventoryService->decrease($technicianLocation, $material, $quantity);
                    $this->inventoryService->increase($warehouse, $material, $quantity);
                    // registrar movimiento de stock
                    $this->movementLogger->log(
                        'transfer_out',
                        $material,
                        null,
                        $technicianLocation,
                        $warehouse,
                        $quantity,
                        'transfer',
                        $transfer->id,
                        $technician->id
                    );
                }
                // actualizar estado de la transferencia
                $transfer->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                ]);
            });
            // capturar excepciones de validacion
        } catch (RuntimeException $exception) {
            return back()->withErrors(['transfer' => $exception->getMessage()]);
        }

        return back()->with('status', 'Devolución aceptada y stock actualizado.');
    }
    // rechazar devolucion de transferencia
    public function reject(Request $request, Transfer $transfer): RedirectResponse
    {   // obtener tecnico y ubicacion
        $technician = $request->user();
        $technicianLocation = $this->ensureTechnicianLocation($technician);
        // validar que la transferencia sea de devolucion y pertenezca al tecnico
        if ($transfer->type !== 'return' || $transfer->from_location_id !== $technicianLocation->id) {
            abort(403);
        }
        // validar estado pendiente
        if ($transfer->status !== 'pending') {
            return back()->withErrors(['transfer' => 'La solicitud ya fue procesada.']);
        }
        // actualizar estado a rechazado
        $transfer->update([
            'status' => 'rejected',
            'rejected_at' => now(),
        ]);
        return back()->with('status', 'Has rechazado la solicitud de devolución.');
    }
    // obtener ubicacion del almacen principal
    protected function warehouseLocation(): StockLocation
    {   // obtener ubicacion del almacen
        $location = StockLocation::warehouses()->first();
        // validar existencia del almacen
        if (! $location) {
            throw new RuntimeException('No se encontró la ubicación del almacén principal.');
        }
        return $location;
    }
    // asegurar que el tecnico tenga una ubicacion de stock
    protected function ensureTechnicianLocation($technician): StockLocation
    {   // obtener ubicacion existente
        $location = $technician->stockLocation()->first();
        // si existe, retornarla
        if ($location) {
            return $location;
        }
        // crear nueva ubicacion de stock para el tecnico si no extiste ubicacion
        return StockLocation::create([
            'location_type' => 'user',
            'ref_id' => $technician->id,
            'name' => 'Stock de ' . $technician->name,
        ]);
    }
}
