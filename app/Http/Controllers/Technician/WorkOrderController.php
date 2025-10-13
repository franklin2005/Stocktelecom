<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Material;
use App\Models\MaterialSerial;
use App\Models\StockLocation;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\InventoryService;
use App\Services\StockMovementLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class WorkOrderController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockMovementLogger $movementLogger,
    ) {
    }

    /**
     * Listado y gestion de ordenes de trabajo del tecnico.
     */
    public function index(Request $request): View
    {
        $technician = $request->user();
        $location = $this->ensureTechnicianLocation($technician);

        $openOrder = WorkOrder::query()
            ->with([
                'items.material',
                'items.serial.material',
            ])
            ->where('technician_id', $technician->id)
            ->where('status', 'open')
            ->orderByDesc('created_at')
            ->first();

        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $location->id)
            ->orderBy('material_id')
            ->get();

        $nonSerializedInventory = $inventory
            ->filter(fn ($item) => $item->material && ! $item->material->is_serialized)
            ->values();

        $serializedInventory = $inventory
            ->filter(fn ($item) => $item->material && $item->material->is_serialized)
            ->values()
            ->keyBy('material_id');

        $serialsInOrder = $openOrder
            ? $openOrder->items->pluck('material_serial_id')->filter()->all()
            : [];

        $availableSerials = MaterialSerial::query()
            ->with('material')
            ->where('current_location_id', $location->id)
            ->where('status', 'assigned')
            ->when(! empty($serialsInOrder), fn ($query) => $query->whereNotIn('id', $serialsInOrder))
            ->orderBy('material_id')
            ->orderBy('serial_number')
            ->get()
            ->groupBy('material_id');

        $workOrders = WorkOrder::query()
            ->withCount('items')
            ->where('technician_id', $technician->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('technician.work-orders', [
            'location' => $location,
            'openOrder' => $openOrder,
            'nonSerializedInventory' => $nonSerializedInventory,
            'serializedInventory' => $serializedInventory,
            'availableSerials' => $availableSerials,
            'workOrders' => $workOrders,
        ]);
    }

    /**
     * Crea una nueva orden de trabajo en estado abierto.
     */
    public function store(Request $request): RedirectResponse
    {
        $technician = $request->user();

        $existingOpenOrder = WorkOrder::query()
            ->where('technician_id', $technician->id)
            ->where('status', 'open')
            ->exists();

        if ($existingOpenOrder) {
            return back()->withErrors([
                'order_number' => 'Ya cuentas con una orden abierta. Confirma o cancela antes de crear una nueva.',
            ])->withInput();
        }

        $validated = $request->validate([
            'order_number' => ['required', 'string', 'max:50', 'unique:work_orders,order_number'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'order_number.required' => 'Ingresa el numero de orden.',
            'order_number.unique' => 'El numero de orden ya existe.',
        ]);

        WorkOrder::create([
            'order_number' => $validated['order_number'],
            'technician_id' => $technician->id,
            'technician_code' => $technician->tech_code ?: ('TEC-' . $technician->id),
            'technician_name' => $technician->name,
            'status' => 'open',
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('technician.work-orders')
            ->with('status', 'Orden de trabajo creada en estado abierta.');
    }

    /**
     * Agrega materiales no serializados a la orden abierta.
     */
    public function addQuantityItem(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $technician = $request->user();
        $this->assertOwnsOpenOrder($workOrder, $technician);

        $validated = $request->validate([
            'material_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
        ], [
            'material_id.required' => 'Selecciona un material valido.',
            'quantity.required' => 'Indica la cantidad a utilizar.',
            'quantity.min' => 'La cantidad debe ser al menos 1.',
        ]);

        $location = $this->ensureTechnicianLocation($technician);

        $material = Material::query()
            ->whereKey($validated['material_id'])
            ->where('is_serialized', false)
            ->first();

        if (! $material) {
            return back()->withErrors(['material_id' => 'El material seleccionado no es valido.']);
        }

        $inventory = Inventory::query()
            ->where('location_id', $location->id)
            ->where('material_id', $material->id)
            ->first();

        $available = $inventory?->quantity ?? 0;
        $requested = (int) $validated['quantity'];

        $existingQuantity = WorkOrderItem::query()
            ->where('work_order_id', $workOrder->id)
            ->where('material_id', $material->id)
            ->whereNull('material_serial_id')
            ->sum('quantity');

        if ($available <= 0 || ($existingQuantity + $requested) > $available) {
            return back()->withErrors([
                'quantity' => 'No cuentas con stock suficiente para este material.',
            ]);
        }

        $item = WorkOrderItem::firstOrNew([
            'work_order_id' => $workOrder->id,
            'material_id' => $material->id,
            'material_serial_id' => null,
        ]);

        $item->quantity = ($item->quantity ?? 0) + $requested;
        $item->save();

        return back()->with('status', 'Se registraron ' . $requested . ' unidades en la orden.');
    }

    /**
     * Agrega materiales serializados a la orden abierta.
     */
    public function addSerialItem(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $technician = $request->user();
        $this->assertOwnsOpenOrder($workOrder, $technician);

        $validated = $request->validate([
            'serial_ids' => ['required', 'array', 'min:1'],
            'serial_ids.*' => ['integer'],
        ], [
            'serial_ids.required' => 'Selecciona al menos un numero de serie.',
        ]);

        $serialIds = collect($validated['serial_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($serialIds->isEmpty()) {
            return back()->withErrors(['serial_ids' => 'Selecciona al menos un numero de serie.']);
        }

        $location = $this->ensureTechnicianLocation($technician);

        $existing = WorkOrderItem::query()
            ->where('work_order_id', $workOrder->id)
            ->whereIn('material_serial_id', $serialIds->all())
            ->pluck('material_serial_id');

        if ($existing->isNotEmpty()) {
            return back()->withErrors([
                'serial_ids' => 'Los numeros de serie ' . $existing->implode(', ') . ' ya forman parte de la orden.',
            ]);
        }

        $serials = MaterialSerial::query()
            ->with('material')
            ->whereIn('id', $serialIds->all())
            ->where('current_location_id', $location->id)
            ->where('status', 'assigned')
            ->get();

        if ($serials->count() !== $serialIds->count()) {
            return back()->withErrors([
                'serial_ids' => 'Algunos numeros de serie ya no estan disponibles en tu stock.',
            ]);
        }

        $serials->each(function (MaterialSerial $serial) use ($workOrder) {
            WorkOrderItem::create([
                'work_order_id' => $workOrder->id,
                'material_id' => $serial->material_id,
                'material_serial_id' => $serial->id,
                'quantity' => null,
            ]);
        });

        return back()->with('status', 'Se agregaron ' . $serials->count() . ' numeros de serie a la orden.');
    }

    /**
     * Elimina un item de la orden abierta.
     */
    public function removeItem(Request $request, WorkOrder $workOrder, WorkOrderItem $item): RedirectResponse
    {
        $technician = $request->user();
        $this->assertOwnsOpenOrder($workOrder, $technician);

        if ($item->work_order_id !== $workOrder->id) {
            abort(403);
        }

        $item->delete();

        return back()->with('status', 'El material se elimino de la orden.');
    }

    /**
     * Confirma la orden y descuenta el stock utilizado.
     */
    public function confirm(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $technician = $request->user();
        $this->assertOwnsOpenOrder($workOrder, $technician);

        $location = $this->ensureTechnicianLocation($technician);

        $workOrder->load(['items.material', 'items.serial.material']);

        if ($workOrder->items->isEmpty()) {
            return back()->withErrors([
                'work_order' => 'Agrega materiales antes de confirmar la orden.',
            ]);
        }

        try {
            DB::transaction(function () use ($workOrder, $location, $technician) {
                $quantityItems = $workOrder->items
                    ->filter(fn (WorkOrderItem $item) => $item->material && $item->material_serial_id === null)
                    ->groupBy('material_id');

                foreach ($quantityItems as $materialId => $items) {
                    $material = $items->first()->material;
                    $totalQuantity = (int) $items->sum('quantity');

                    if ($totalQuantity < 1) {
                        continue;
                    }

                    $this->inventoryService->decrease($location, $material, $totalQuantity);

                    $this->movementLogger->log(
                        'consumption',
                        $material,
                        null,
                        $location,
                        null,
                        $totalQuantity,
                        'work_order',
                        $workOrder->id,
                        $technician->id
                    );
                }

                $serialItems = $workOrder->items
                    ->filter(fn (WorkOrderItem $item) => $item->serial !== null);

                if ($serialItems->isNotEmpty()) {
                    $serialIds = $serialItems->pluck('material_serial_id')->unique()->values()->all();

                    /** @var Collection<int, MaterialSerial> $serialModels */
                    $serialModels = MaterialSerial::query()
                        ->whereIn('id', $serialIds)
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    if ($serialModels->count() !== count($serialIds)) {
                        throw new RuntimeException('Algunos numeros de serie ya no estan disponibles.');
                    }

                    foreach ($serialItems as $item) {
                        $serial = $serialModels[$item->material_serial_id];
                        $material = $item->material;

                        if ($serial->current_location_id !== $location->id) {
                            throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya no esta en tu stock.');
                        }

                        if ($serial->status !== 'assigned') {
                            throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya no esta disponible para usar.');
                        }

                        $serial->update([
                            'status' => 'installed',
                            'current_location_id' => null,
                            'reserved_by_user_id' => null,
                            'reserved_at' => null,
                        ]);

                        $this->inventoryService->decrease($location, $material, 1);

                        $this->movementLogger->log(
                            'consumption',
                            $material,
                            $serial,
                            $location,
                            null,
                            1,
                            'work_order',
                            $workOrder->id,
                            $technician->id
                        );
                    }
                }

                $workOrder->update(['status' => 'confirmed']);
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'work_order' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('technician.work-orders')
            ->with('status', 'Orden confirmada correctamente.');
    }

    /**
     * Cancela la orden abierta.
     */
    public function cancel(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $technician = $request->user();
        $this->assertOwnsOpenOrder($workOrder, $technician);

        $workOrder->update(['status' => 'cancelled']);

        return redirect()->route('technician.work-orders')
            ->with('status', 'Orden cancelada.');
    }

    private function assertOwnsOpenOrder(WorkOrder $workOrder, User $technician): void
    {
        if (
            $workOrder->technician_id !== $technician->id ||
            $workOrder->status !== 'open'
        ) {
            abort(403);
        }
    }

    private function ensureTechnicianLocation(User $technician): StockLocation
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
}
