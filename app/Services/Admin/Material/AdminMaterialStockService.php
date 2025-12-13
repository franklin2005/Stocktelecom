<?php

namespace App\Services\Admin\Material;

use App\Models\Material;
use App\Models\MaterialSerial;
use App\Models\StockLocation;
use App\Services\InventoryService;
use App\Services\StockMovementLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminMaterialStockService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockMovementLogger $movementLogger,
        private readonly AdminMaterialLocationService $locationService,
    ) {
    }
    // agregar stock de material al almacen
    public function addStock(Request $request, Material $material): RedirectResponse
    {   // verificar si el material esta activo
        if (! $material->is_active) {
            return back()->withErrors([
                'material_id' => 'El material esto inactivo. Actívalo antes de registrar stock.',
            ])->withInput();
        }
        // obtener ubicacion del almacen
        $warehouseLocation = $this->locationService->warehouseLocation();
        $userId = auth()->id();
        // procesar segun si el material es serializado
        if ($material->is_serialized) {
            $serialNumbers = $this->parseSerialNumbers($request->input('serial_numbers'));
            // validar que se hayan proporcionado numeros de serie
            if ($serialNumbers->isEmpty()) {
                return back()->withErrors([
                    'serial_numbers' => 'Debes indicar al menos un numero de serie.',
                ])->withInput();
            }
            // verificar duplicados en la base de datos
            $duplicated = MaterialSerial::query()
                ->whereIn('serial_number', $serialNumbers->all())
                ->pluck('serial_number')
                ->toArray();
            // si hay duplicados, retornar error
            if (! empty($duplicated)) {
                return back()->withErrors([
                    'serial_numbers' => 'Los siguientes numeros de serie ya existen: ' . implode(', ', $duplicated),
                ])->withInput();
            }
            // agregar cada numero de serie
            DB::transaction(function () use ($serialNumbers, $material, $warehouseLocation, $userId) {
                foreach ($serialNumbers as $serial) {
                    $serialModel = MaterialSerial::create([
                        'material_id' => $material->id,
                        'serial_number' => $serial,
                        'status' => 'available',
                        'current_location_id' => $warehouseLocation->id,
                    ]);
                    // registrar movimiento de inventario
                    $this->movementLogger->log(
                        'adjustment',
                        $material,
                        $serialModel,
                        null,
                        $warehouseLocation,
                        1,
                        'manual_adjustment',
                        0,
                        $userId
                    );
                }
                // actualizar inventario
                $this->inventoryService->increase($warehouseLocation, $material, $serialNumbers->count());
            });
            // retornar exito
            return redirect()
                ->route('admin.materials')
                ->with('status', 'Series agregadas al almacen correctamente.');
        }
        // procesar material no serializado
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ], [
            'quantity.required' => 'Debes indicar una cantidad a ingresar.',
            'quantity.min' => 'La cantidad debe ser mayor a cero.',
        ]);
        // agregar stock en transaccion
        DB::transaction(function () use ($material, $warehouseLocation, $validated, $userId) {
            $this->inventoryService->increase($warehouseLocation, $material, (int) $validated['quantity']);
            // registrar movimiento de inventario
            $this->movementLogger->log(
                'adjustment',
                $material,
                null,
                null,
                $warehouseLocation,
                (int) $validated['quantity'],
                'manual_adjustment',
                0,
                $userId
            );
        });
        // retornar con exito
        return redirect()
            ->route('admin.materials')
            ->with('status', 'Stock actualizado en el almacen.');
    }
    // eliminar stock de material del almacen
    public function removeStock(Request $request, Material $material): RedirectResponse
    {   // verificar si el material esta activo
        if (! $material->is_active) {
            return back()->withErrors([
                'material_id' => 'El material esto inactivo. Actívalo antes de retirar stock.',
            ])->withInput();
        }
        // obtener ubicacion del almacen
        $warehouseLocation = $this->locationService->warehouseLocation();
        $userId = auth()->id();
        // procesar segun si el material es serializado
        try {
            if ($material->is_serialized) {
                $serialIds = collect($request->input('serial_ids', []))
                    ->map(fn ($id) => (int) $id)
                    ->filter();
                // validar que se hayan proporcionado numeros de serie
                if ($serialIds->isEmpty()) {
                    return back()->withErrors([
                        'serial_ids' => 'Debes seleccionar al menos un numero de serie.',
                    ])->withInput();
                }
                // eliminar cada numero de serie
                DB::transaction(function () use ($serialIds, $material, $warehouseLocation, $userId) {
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
                    // eliminar cada serial
                    foreach ($serials as $serial) {
                        $serial->update([
                            'status' => 'scrapped',
                            'current_location_id' => null,
                        ]);
                        // registrar movimiento de inventario
                        $this->movementLogger->log(
                            'adjustment',
                            $material,
                            $serial,
                            $warehouseLocation,
                            null,
                            1,
                            'manual_adjustment',
                            0,
                            $userId
                        );
                    }
                    // actualizar inventario
                    $this->inventoryService->decrease($warehouseLocation, $material, $serials->count());
                });
            } else { // material no serializado
                $validated = $request->validate([
                    'quantity' => ['required', 'integer', 'min:1'],
                ], [
                    'quantity.required' => 'Debes indicar la cantidad a retirar.',
                    'quantity.min' => 'La cantidad debe ser mayor a cero.',
                ]);
                // eliminar stock en transaccion
                DB::transaction(function () use ($material, $warehouseLocation, $validated, $userId) {
                    $this->inventoryService->decrease($warehouseLocation, $material, (int) $validated['quantity']);
                    // registrar movimiento de inventario
                    $this->movementLogger->log(
                        'adjustment',
                        $material,
                        null,
                        $warehouseLocation,
                        null,
                        (int) $validated['quantity'],
                        'manual_adjustment',
                        0,
                        $userId
                    );
                });
            }   
        } catch (RuntimeException $exception) {// manejar errores de stock insuficiente
            return back()->withErrors([
                'remove_error' => $exception->getMessage(),
            ])->withInput();
        }

        return redirect()
            ->route('admin.materials')
            ->with('status', 'Stock eliminado del almacen.');
    }
    // parsear numeros de serie desde texto bruto
    protected function parseSerialNumbers(?string $raw)
    {// si no hay texto, retornar coleccion vacia
        if (! $raw) {
            return collect();
        }
        // separar por saltos de linea, comas o puntos y comas, limpiar espacios y eliminar duplicados
        return collect(preg_split('/[\r\n,;]+/', $raw))
            ->map(fn ($serial) => trim($serial))
            ->filter()
            ->unique();
    }
}
