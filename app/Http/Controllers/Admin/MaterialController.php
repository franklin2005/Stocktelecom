<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialSerial;
use App\Models\StockLocation;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\StockMovementLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use RuntimeException;

class MaterialController extends Controller
{
    private const CATEGORY_MAP = [
        'equipo' => 'equipment',
        'acometida' => 'acometida',
        'roseta' => 'roseta',
        'otro' => 'other',
    ];

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockMovementLogger $movementLogger,
    ) {
    }

    /**
     * Muestra el inventario del almacen y formularios de gestion.
     */
    public function index(): View
    {
        $warehouseLocation = $this->warehouseLocation();

        $materials = Material::query()
            ->with([
                'inventories' => function ($query) use ($warehouseLocation) {
                    $query->where('location_id', $warehouseLocation->id);
                },
                'serials' => function ($query) use ($warehouseLocation) {
                    $query->where('current_location_id', $warehouseLocation->id)
                        ->where('status', 'available');
                },
            ])
            ->orderBy('category')
            ->orderBy('type')
            ->orderBy('model')
            ->get();

        $technicians = User::technicians()
            ->orderBy('name')
            ->with('stockLocation')
            ->get();

        return view('admin.materials', [
            'materials' => $materials,
            'warehouseLocation' => $warehouseLocation,
            'technicians' => $technicians,
            'canManageWarehouse' => $this->canManageWarehouse(),
        ]);
    }

    /**
     * Formulario de creación de materiales.
     */
    public function create(): View
    {
        $categoryOptions = array_keys(self::CATEGORY_MAP);

        $typeSuggestions = [
            'equipo' => ['router', 'ont', 'decodificador', 'mando'],
            'acometida' => ['ZTE', 'Huawei', 'Corning', '3M', 'Mixta', 'Interior'],
            'roseta' => ['Final', 'Transición'],
        ];

        $defaultSerialized = [
            'equipo' => true,
            'acometida' => false,
            'roseta' => false,
            'otro' => false,
        ];

        $materials = Material::query()
            ->orderBy('category')
            ->orderBy('type')
            ->orderBy('model')
            ->get();

        $reverseCategoryMap = array_flip(self::CATEGORY_MAP);

        return view('admin.materials.create', [
            'categoryOptions' => $categoryOptions,
            'typeSuggestions' => $typeSuggestions,
            'defaultSerialized' => $defaultSerialized,
            'materials' => $materials,
            'categoryMap' => self::CATEGORY_MAP,
            'reverseCategoryMap' => $reverseCategoryMap,
        ]);
    }

    /**
     * Persiste el nuevo material base.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'in:equipo,acometida,roseta,otro'],
            'type' => ['required', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:150'],
            'is_serialized' => ['required', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = strtolower(trim($validated['category']));
        $type = trim($validated['type']);
        $model = array_key_exists('model', $validated) && $validated['model'] !== null
            ? trim($validated['model'])
            : null;
        if ($model === '') {
            $model = null;
        }

        $databaseCategory = self::CATEGORY_MAP[$category] ?? $category;

        $normalizedCategory = mb_strtolower($databaseCategory, 'UTF-8');
        $normalizedType = mb_strtolower($type, 'UTF-8');
        $normalizedModel = $model !== null ? mb_strtolower($model, 'UTF-8') : null;

        $duplicateQuery = Material::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(category) = ?', [$normalizedCategory])
            ->whereRaw('LOWER(type) = ?', [$normalizedType]);

        if ($normalizedModel === null) {
            $duplicateQuery->whereNull('model');
        } else {
            $duplicateQuery->whereRaw('LOWER(model) = ?', [$normalizedModel]);
        }

        if ($duplicateQuery->exists()) {
            return back()->withErrors([
                'type' => 'Ya existe un material activo con la misma categoría, tipo y modelo.',
            ])->withInput();
        }

        $isSerialized = $request->boolean('is_serialized');

        $isActive = $request->boolean('is_active');

        $material = Material::create([
            'category' => $databaseCategory,
            'type' => $type,
            'model' => $model,
            'is_serialized' => $isSerialized,
            'is_active' => $isActive,
        ]);

        session()->flash('status', 'Material creado correctamente.');

        return redirect()
            ->route('admin.materials.create')
            ->with('highlight_material_id', $material->id);
    }

    /**
     * Actualiza un material existente.
     */
    public function update(Request $request, Material $material): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'category' => ['required', 'string', 'in:equipo,acometida,roseta,otro'],
            'type' => ['required', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:150'],
            'is_serialized' => ['required', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('admin.materials.create')
                ->withErrors($validator, 'updateMaterial')
                ->withInput()
                ->with('editing_material_id', $material->id);
        }

        $validated = $validator->validated();

        $categoryKey = strtolower(trim($validated['category']));
        $type = trim($validated['type']);
        $model = array_key_exists('model', $validated) && $validated['model'] !== null
            ? trim($validated['model'])
            : null;
        if ($model === '') {
            $model = null;
        }

        $databaseCategory = self::CATEGORY_MAP[$categoryKey] ?? $categoryKey;

        $isSerialized = $request->boolean('is_serialized');
        $isActive = $request->boolean('is_active');

        if ($isActive) {
            $normalizedCategory = mb_strtolower($databaseCategory, 'UTF-8');
            $normalizedType = mb_strtolower($type, 'UTF-8');
            $normalizedModel = $model !== null ? mb_strtolower($model, 'UTF-8') : null;

            $duplicateQuery = Material::query()
                ->where('id', '!=', $material->id)
                ->where('is_active', true)
                ->whereRaw('LOWER(category) = ?', [$normalizedCategory])
                ->whereRaw('LOWER(type) = ?', [$normalizedType]);

            if ($normalizedModel === null) {
                $duplicateQuery->whereNull('model');
            } else {
                $duplicateQuery->whereRaw('LOWER(model) = ?', [$normalizedModel]);
            }

            if ($duplicateQuery->exists()) {
                return redirect()
                    ->route('admin.materials.create')
                    ->withErrors([
                        'type' => 'Ya existe un material activo con la misma categoría, tipo y modelo.',
                    ], 'updateMaterial')
                    ->withInput()
                    ->with('editing_material_id', $material->id);
            }
        }

        $material->update([
            'category' => $databaseCategory,
            'type' => $type,
            'model' => $model,
            'is_serialized' => $isSerialized,
            'is_active' => $isActive,
        ]);

        session()->flash('status', 'Material actualizado correctamente.');

        return redirect()
            ->route('admin.materials.create')
            ->with('highlight_material_id', $material->id);
    }

    /**
     * Elimina un material si no tiene dependencias.
     */
    public function destroy(Material $material): RedirectResponse
    {
        $hasDependencies = $material->serials()->exists()
            || $material->inventories()->exists()
            || $material->transferItems()->exists()
            || $material->workOrderItems()->exists()
            || DB::table('stock_movements')->where('material_id', $material->id)->exists();

        if ($hasDependencies) {
            return redirect()
                ->route('admin.materials.create')
                ->with('material_error', 'No es posible eliminar el material porque tiene movimientos, inventario o series asociadas.')
                ->with('highlight_material_id', $material->id);
        }

        $material->delete();

        session()->flash('status', 'Material eliminado correctamente.');

        return redirect()->route('admin.materials.create');
    }

    /**
     * Registra nuevas existencias en el almacen principal.
     */
    public function addStock(Request $request): RedirectResponse
    {
        $this->ensureCanManageWarehouse();

        $material = Material::findOrFail($request->input('material_id'));

        if (! $material->is_active) {
            return back()->withErrors([
                'material_id' => 'El material está inactivo. Actívalo antes de registrar stock.',
            ])->withInput();
        }

        $warehouseLocation = $this->warehouseLocation();

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

    /**
     * Genera una transferencia hacia un tecnico a la espera de aceptacion.
     */
    public function assignToTechnician(Request $request): RedirectResponse
    {
        $this->ensureCanManageWarehouse();

        $material = Material::findOrFail($request->input('material_id'));

        if (! $material->is_active) {
            return back()->withErrors([
                'material_id' => 'El material está inactivo. Actívalo antes de generar nuevas asignaciones.',
            ])->withInput();
        }

        $technician = User::technicians()->whereKey($request->input('technician_id'))->first();

        if (! $technician) {
            return back()->withErrors([
                'technician_id' => 'Selecciona un tecnico valido.',
            ])->withInput();
        }

        $warehouseLocation = $this->warehouseLocation();
        $technicianLocation = $this->ensureTechnicianLocation($technician);

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

    /**
     * Elimina existencias del almacen principal.
     */
    public function removeStock(Request $request): RedirectResponse
    {
        $this->ensureCanManageWarehouse();

        $material = Material::findOrFail($request->input('material_id'));

        if (! $material->is_active) {
            return back()->withErrors([
                'material_id' => 'El material está inactivo. Actívalo antes de retirar stock.',
            ])->withInput();
        }

        $warehouseLocation = $this->warehouseLocation();
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

    /**
     * Obtiene la ubicacion del almacen principal.
     */
    protected function warehouseLocation(): StockLocation
    {
        $location = StockLocation::warehouses()->first();

        if (! $location) {
            throw new RuntimeException('No se encontro la ubicacion de almacen.');
        }

        return $location;
    }

    /**
     * Garantiza que el tecnico cuente con una ubicacion de stock asignada.
     */
    protected function ensureTechnicianLocation(User $technician): StockLocation
    {
        $location = $technician->stockLocation()->first();

        if ($location) {
            return $location;
        }

        return StockLocation::create([
            'location_type' => 'user',
            'ref_id' => $technician->id,
            'name' => 'Stock de ' . $technician->name,
        ]);
    }

    /**
     * Genera un numero de orden unico para la transferencia.
     */
    protected function generateTransferNumber(): string
    {
        do {
            $number = 'TRF-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Transfer::where('order_number', $number)->exists());

        return $number;
    }

    /**
     * Limpia y normaliza la lista de numeros de serie.
     */
    protected function parseSerialNumbers(?string $raw): Collection
    {
        if (! $raw) {
            return collect();
        }

        return collect(preg_split('/[\r\n,;]+/', $raw))
            ->map(fn ($serial) => trim($serial))
            ->filter()
            ->unique();
    }

    /**
     * Determina si el usuario actual puede gestionar el almacen.
     */
    protected function canManageWarehouse(): bool
    {
        $role = auth()->user()?->role;

        return in_array($role, ['super_admin', 'logistics'], true);
    }

    /**
     * Garantiza que solo perfiles autorizados gestionen movimientos.
     */
    protected function ensureCanManageWarehouse(): void
    {
        if (! $this->canManageWarehouse()) {
            abort(403);
        }
    }
}
