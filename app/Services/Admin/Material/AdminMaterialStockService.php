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

    public function addStock(Request $request, Material $material): RedirectResponse
    {
        if (! $material->is_active) {
            return back()->withErrors([
                'material_id' => 'El material esto inactivo. Act??valo antes de registrar stock.',
            ])->withInput();
        }

        $warehouseLocation = $this->locationService->warehouseLocation();
        $userId = auth()->id();

        if ($material->is_serialized) {
            $serialNumbers = $this->parseSerialNumbers($request->input('serial_numbers'));

            if ($serialNumbers->isEmpty()) {
                return back()->withErrors([
                    'serial_numbers' => 'Debes indicar al menos un numero de serie.',
                ])->withInput();
            }

            $duplicated = MaterialSerial::query()
                ->whereIn('serial_number', $serialNumbers->all())
                ->pluck('serial_number')
                ->toArray();

            if (! empty($duplicated)) {
                return back()->withErrors([
                    'serial_numbers' => 'Los siguientes numeros de serie ya existen: ' . implode(', ', $duplicated),
                ])->withInput();
            }

            DB::transaction(function () use ($serialNumbers, $material, $warehouseLocation, $userId) {
                foreach ($serialNumbers as $serial) {
                    $serialModel = MaterialSerial::create([
                        'material_id' => $material->id,
                        'serial_number' => $serial,
                        'status' => 'available',
                        'current_location_id' => $warehouseLocation->id,
                    ]);

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

                $this->inventoryService->increase($warehouseLocation, $material, $serialNumbers->count());
            });

            return redirect()
                ->route('admin.materials')
                ->with('status', 'Series agregadas al almacen correctamente.');
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ], [
            'quantity.required' => 'Debes indicar una cantidad a ingresar.',
            'quantity.min' => 'La cantidad debe ser mayor a cero.',
        ]);

        DB::transaction(function () use ($material, $warehouseLocation, $validated, $userId) {
            $this->inventoryService->increase($warehouseLocation, $material, (int) $validated['quantity']);

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

        return redirect()
            ->route('admin.materials')
            ->with('status', 'Stock actualizado en el almacen.');
    }

    public function removeStock(Request $request, Material $material): RedirectResponse
    {
        if (! $material->is_active) {
            return back()->withErrors([
                'material_id' => 'El material esto inactivo. Act??valo antes de retirar stock.',
            ])->withInput();
        }

        $warehouseLocation = $this->locationService->warehouseLocation();
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

                DB::transaction(function () use ($serialIds, $material, $warehouseLocation, $userId) {
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

                    foreach ($serials as $serial) {
                        $serial->update([
                            'status' => 'scrapped',
                            'current_location_id' => null,
                        ]);

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

                    $this->inventoryService->decrease($warehouseLocation, $material, $serials->count());
                });
            } else {
                $validated = $request->validate([
                    'quantity' => ['required', 'integer', 'min:1'],
                ], [
                    'quantity.required' => 'Debes indicar la cantidad a retirar.',
                    'quantity.min' => 'La cantidad debe ser mayor a cero.',
                ]);

                DB::transaction(function () use ($material, $warehouseLocation, $validated, $userId) {
                    $this->inventoryService->decrease($warehouseLocation, $material, (int) $validated['quantity']);

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
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'remove_error' => $exception->getMessage(),
            ])->withInput();
        }

        return redirect()
            ->route('admin.materials')
            ->with('status', 'Stock eliminado del almacen.');
    }

    protected function parseSerialNumbers(?string $raw)
    {
        if (! $raw) {
            return collect();
        }

        return collect(preg_split('/[\r\n,;]+/', $raw))
            ->map(fn ($serial) => trim($serial))
            ->filter()
            ->unique();
    }
}
