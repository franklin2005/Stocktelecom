<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
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
use Illuminate\View\View;
use RuntimeException;

class AdminTransferController extends Controller
{
    private const CART_SESSION_KEY = 'admin_transfer_cart';

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockMovementLogger $movementLogger,
    ) {
    }

    /**
     * Display warehouse inventory and transfer cart for logistics/super admins.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        if (! $this->canInitiateTransfers($user)) {
            abort(403);
        }

        $warehouse = $this->warehouseLocation();

        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $warehouse->id)
            ->orderBy('material_id')
            ->get();

        $availableSerials = MaterialSerial::query()
            ->with('material')
            ->where('current_location_id', $warehouse->id)
            ->whereIn('status', ['available', 'reserved'])
            ->where(function ($query) use ($user) {
                $query->whereNull('reserved_by_user_id')
                    ->orWhere('reserved_by_user_id', $user->id);
            })
            ->orderBy('material_id')
            ->orderBy('serial_number')
            ->get();

        $cartData = $this->prepareCart($request, $warehouse, $inventory, $availableSerials, (int) $user->id);
        $cartItems = $cartData['items'];
        $cartSummary = $cartData['summary'];
        $reservedQuantities = $cartData['reserved_quantities'];
        $serialsInCart = $cartData['serials_in_cart'];

        if (! empty($cartData['warnings'])) {
            $request->session()->flash('cart_warning', implode(' ', $cartData['warnings']));
        }

        $recipientSearch = trim((string) $request->query('recipient_search', ''));
        $recipientQuery = User::technicians()->orderBy('name');

        if ($recipientSearch !== '') {
            $recipientQuery->where('name', 'like', '%' . $recipientSearch . '%');
        }

        $technicians = $recipientQuery->limit(25)->get();

        $nonSerializedInventory = $inventory->filter(fn ($item) => $item->material && ! $item->material->is_serialized)->values();
        $serializedInventory = $inventory->filter(fn ($item) => $item->material && $item->material->is_serialized)->values()->keyBy('material_id');

        return view('admin.transfers', [
            'warehouse' => $warehouse,
            'nonSerializedInventory' => $nonSerializedInventory,
            'serializedInventory' => $serializedInventory,
            'availableSerials' => $availableSerials,
            'cartItems' => $cartItems,
            'cartSummary' => $cartSummary,
            'reservedQuantities' => $reservedQuantities,
            'serialsInCart' => $serialsInCart,
            'technicians' => $technicians,
            'recipientSearch' => $recipientSearch,
        ]);
    }

    /**
     * Add warehouse materials to the transfer cart.
     */
    public function addToCart(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->canInitiateTransfers($user)) {
            abort(403);
        }

        $warehouse = $this->warehouseLocation();
        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $warehouse->id)
            ->get();

        $intent = $request->input('intent');

        if (! in_array($intent, ['quantity', 'serial'], true)) {
            return back()->withErrors(['cart' => 'No se pudo determinar la accion solicitada.']);
        }

        $cart = $this->getCart($request);

        if ($intent === 'quantity') {
            $validated = $request->validate([
                'material_id' => ['required', 'integer'],
                'quantity' => ['required', 'integer', 'min:1'],
            ]);

            $material = Material::query()
                ->whereKey($validated['material_id'])
                ->where('is_serialized', false)
                ->first();

            if (! $material) {
                return back()->withErrors(['material_id' => 'El material seleccionado no esta disponible.']);
            }

            $available = $inventory->firstWhere('material_id', $material->id)?->quantity ?? 0;
            $reserved = $cart['quantity-' . $material->id]['quantity'] ?? 0;
            $requested = (int) $validated['quantity'];

            if ($available <= 0) {
                return back()->withErrors(['material_id' => 'No hay stock disponible de este material en el almacen.']);
            }

            if ($requested + $reserved > $available) {
                return back()->withErrors(['quantity' => 'Solo hay ' . max($available - $reserved, 0) . ' unidades disponibles.']);
            }

            $cart['quantity-' . $material->id] = [
                'type' => 'quantity',
                'material_id' => $material->id,
                'quantity' => $requested + $reserved,
            ];

            $this->saveCart($request, $cart);

            return back()->with('status', 'Se agregaron ' . $requested . ' unidades de ' . ucfirst($material->type) . ' a la lista.');
        }

        $validated = $request->validate([
            'serial_ids' => ['required', 'array', 'min:1'],
            'serial_ids.*' => ['integer'],
        ]);

        $serialIds = collect($validated['serial_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $duplicates = $serialIds->filter(fn ($id) => isset($cart['serial-' . $id]));

        if ($duplicates->isNotEmpty()) {
            return back()->withErrors(['serial_ids' => 'Los numeros de serie ' . $duplicates->implode(', ') . ' ya estan en la lista.']);
        }

        try {
            $reservedSerials = $this->reserveSerials($warehouse, $serialIds->all(), (int) $user->id);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['serial_ids' => $exception->getMessage()]);
        }

        foreach ($reservedSerials as $serial) {
            $cart['serial-' . $serial->id] = [
                'type' => 'serial',
                'material_id' => $serial->material_id,
                'serial_id' => $serial->id,
            ];
        }

        $this->saveCart($request, $cart);

        return back()->with('status', 'Se agregaron ' . $reservedSerials->count() . ' numeros de serie a la lista.');
    }

    /**
     * Remove an item from the cart.
     */
    public function removeFromCart(Request $request, string $itemKey): RedirectResponse
    {
        $user = $request->user();

        if (! $this->canInitiateTransfers($user)) {
            abort(403);
        }

        $cart = $this->getCart($request);

        if (! isset($cart[$itemKey])) {
            return back()->withErrors(['cart' => 'El elemento seleccionado ya no estaba en la lista.']);
        }

        $item = $cart[$itemKey];

        if (($item['type'] ?? null) === 'serial' && isset($item['serial_id'])) {
            $this->releaseSerialReservations([(int) $item['serial_id']], (int) $user->id);
        }

        unset($cart[$itemKey]);
        $this->saveCart($request, $cart);

        return back()->with('status', 'Elemento retirado de la lista.');
    }

    /**
     * Clear cart entirely.
     */
    public function clearCart(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->canInitiateTransfers($user)) {
            abort(403);
        }

        $cart = $this->getCart($request);

        $serialIds = collect($cart)
            ->filter(fn ($item) => is_array($item) && ($item['type'] ?? null) === 'serial' && isset($item['serial_id']))
            ->map(fn ($item) => (int) $item['serial_id'])
            ->values()
            ->all();

        if (! empty($serialIds)) {
            $this->releaseSerialReservations($serialIds, (int) $user->id);
        }

        $this->clearCartSession($request);

        return back()->with('status', 'Se vacio la lista de transferencia.');
    }

    /**
     * Generate transfer from warehouse to technician.
     */
    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->canInitiateTransfers($user)) {
            abort(403);
        }

        $warehouse = $this->warehouseLocation();
        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $warehouse->id)
            ->get();
        $availableSerials = MaterialSerial::query()
            ->with('material')
            ->where('current_location_id', $warehouse->id)
            ->whereIn('status', ['available', 'reserved'])
            ->where(function ($query) use ($user) {
                $query->whereNull('reserved_by_user_id')
                    ->orWhere('reserved_by_user_id', $user->id);
            })
            ->get();

        $cartData = $this->prepareCart($request, $warehouse, $inventory, $availableSerials, (int) $user->id);
        $cartItems = $cartData['items'];
        $cartSummary = $cartData['summary'];

        if (! empty($cartData['warnings'])) {
            return back()->withErrors(['cart' => implode(' ', $cartData['warnings'])]);
        }

        if (empty($cartItems)) {
            return back()->withErrors(['cart' => 'Agrega materiales antes de generar una transferencia.']);
        }

        $technician = User::technicians()->whereKey((int) $request->input('technician_id'))->first();

        if (! $technician) {
            return back()->withErrors(['technician_id' => 'Selecciona un tecnico valido.']);
        }

        $technicianLocation = $this->ensureTechnicianLocation($technician);
        $userId = (int) $user->id;

        $materialIds = collect($cartItems)->pluck('material_id')->filter()->unique()->values();
        $materials = Material::query()->whereIn('id', $materialIds)->get()->keyBy('id');

        $serialIds = collect($cartItems)
            ->where('type', 'serial')
            ->pluck('serial_id')
            ->filter()
            ->unique()
            ->values();

        try {
            DB::transaction(function () use ($warehouse, $technicianLocation, $userId, $materials, $serialIds, $cartItems) {
                $transfer = Transfer::create([
                    'order_number' => $this->generateTransferNumber(),
                    'type' => 'transfer',
                    'from_location_id' => $warehouse->id,
                    'to_location_id' => $technicianLocation->id,
                    'initiator_user_id' => $userId,
                    'requires_receiver_accept' => true,
                    'status' => 'pending',
                    'notes' => null,
                ]);

                $serialModels = $serialIds->isEmpty()
                    ? collect()
                    : MaterialSerial::query()
                        ->whereIn('id', $serialIds->all())
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                foreach ($cartItems as $item) {
                    $material = $materials[$item['material_id']] ?? null;

                    if (! $material) {
                        throw new RuntimeException('No se pudo recuperar el material seleccionado.');
                    }

                    if ($item['type'] === 'quantity') {
                        $this->inventoryService->decrease($warehouse, $material, (int) $item['quantity']);

                        TransferItem::create([
                            'transfer_id' => $transfer->id,
                            'material_id' => $material->id,
                            'quantity' => (int) $item['quantity'],
                        ]);

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

                    $serial = $serialModels[$item['serial_id']] ?? null;

                    if (! $serial || $serial->current_location_id !== $warehouse->id) {
                        throw new RuntimeException('Alguno de los numeros de serie seleccionados ya no esta disponible.');
                    }

                    if ((int) $serial->reserved_by_user_id !== $userId || $serial->status !== 'reserved') {
                        throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya no esta reservado para tu usuario.');
                    }

                    $serial->update([
                        'status' => 'assigned',
                        'current_location_id' => null,
                        'reserved_by_user_id' => null,
                        'reserved_at' => null,
                    ]);

                    $this->inventoryService->decrease($warehouse, $material, 1);

                    TransferItem::create([
                        'transfer_id' => $transfer->id,
                        'material_id' => $material->id,
                        'material_serial_id' => $serial->id,
                    ]);

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
        } catch (RuntimeException $exception) {
            return back()->withErrors(['cart' => $exception->getMessage()]);
        }

        $this->clearCartSession($request);

        return redirect()->route('admin.transfers')
            ->with('status', 'Transferencia generada: ' . $cartSummary['total_units'] . ' elementos enviados.');
    }

    protected function prepareCart(Request $request, StockLocation $warehouse, $inventory, $serials, int $userId)
    {
        $cart = $this->getCart($request);

        if (empty($cart)) {
            return [
                'items' => [],
                'summary' => ['total_items' => 0, 'total_units' => 0],
                'reserved_quantities' => [],
                'serials_in_cart' => [],
                'warnings' => [],
            ];
        }

        $inventoryLookup = collect($inventory)->keyBy('material_id');
        $serialLookup = collect($serials)->keyBy('id');

        $materialIds = [];
        $serialIds = [];

        foreach ($cart as $entry) {
            if (! is_array($entry) || ! isset($entry['type'], $entry['material_id'])) {
                continue;
            }

            $materialIds[] = (int) $entry['material_id'];

            if ($entry['type'] === 'serial' && isset($entry['serial_id'])) {
                $serialIds[] = (int) $entry['serial_id'];
            }
        }

        $materials = Material::query()->whereIn('id', array_unique($materialIds))->get()->keyBy('id');

        $items = [];
        $summary = ['total_items' => 0, 'total_units' => 0];
        $reservedQuantities = [];
        $serialsInCart = [];
        $dirty = false;
        $warnings = [];
        $serialsToRelease = [];

        foreach ($cart as $key => $entry) {
            if (! is_array($entry) || ! isset($entry['type'], $entry['material_id'])) {
                unset($cart[$key]);
                $dirty = true;
                continue;
            }

            $materialId = (int) $entry['material_id'];
            $material = $materials->get($materialId);

            if (! $material) {
                unset($cart[$key]);
                $dirty = true;
                continue;
            }

            if ($entry['type'] === 'quantity') {
                $quantity = (int) ($entry['quantity'] ?? 0);
                $available = $inventoryLookup->get($materialId)?->quantity ?? 0;

                if ($quantity < 1 || $available < 1) {
                    unset($cart[$key]);
                    $dirty = true;
                    continue;
                }

                if ($quantity > $available) {
                    $quantity = $available;
                    $cart[$key]['quantity'] = $quantity;
                    $dirty = true;
                }

                if ($quantity < 1) {
                    unset($cart[$key]);
                    continue;
                }

                $reservedQuantities['quantity-' . $materialId] = $quantity;
                $items[] = [
                    'key' => $key,
                    'type' => 'quantity',
                    'material_id' => $materialId,
                    'material' => $material,
                    'quantity' => $quantity,
                ];

                $summary['total_items']++;
                $summary['total_units'] += $quantity;
                continue;
            }

            if ($entry['type'] === 'serial' && isset($entry['serial_id'])) {
                $serialId = (int) $entry['serial_id'];
                $serial = $serialLookup->get($serialId);

                if (! $serial) {
                    unset($cart[$key]);
                    $dirty = true;
                    $warnings[] = 'Un numero de serie seleccionado ya no existe y se retiro de la lista.';
                    continue;
                }

                if ($serial->current_location_id !== $warehouse->id) {
                    if ((int) $serial->reserved_by_user_id === $userId) {
                        $serialsToRelease[] = $serial->id;
                    }

                    unset($cart[$key]);
                    $dirty = true;
                    $warnings[] = 'El numero de serie ' . $serial->serial_number . ' ya no esta en el almacen y se retiro de la lista.';
                    continue;
                }

                if ((int) $serial->reserved_by_user_id !== $userId || $serial->status !== 'reserved') {
                    if ((int) $serial->reserved_by_user_id === $userId) {
                        $serialsToRelease[] = $serial->id;
                    }

                    if ($serial->reserved_by_user_id && (int) $serial->reserved_by_user_id !== $userId) {
                        $warnings[] = 'El numero de serie ' . $serial->serial_number . ' fue reservado por otro usuario y se retiro de la lista.';
                    } else {
                        $warnings[] = 'El numero de serie ' . $serial->serial_number . ' ya no esta disponible y se retiro de la lista.';
                    }

                    unset($cart[$key]);
                    $dirty = true;
                    continue;
                }

                $serialsInCart[] = $serialId;
                $items[] = [
                    'key' => $key,
                    'type' => 'serial',
                    'material_id' => $materialId,
                    'serial_id' => $serialId,
                    'material' => $material,
                    'serial' => $serial,
                ];

                $summary['total_items']++;
                $summary['total_units']++;
                continue;
            }

            unset($cart[$key]);
            $dirty = true;
        }

        if (! empty($serialsToRelease)) {
            $this->releaseSerialReservations(array_values(array_unique($serialsToRelease)), $userId);
        }

        if ($dirty) {
            $this->saveCart($request, $cart);
        }

        return [
            'items' => array_values($items),
            'summary' => $summary,
            'reserved_quantities' => $reservedQuantities,
            'serials_in_cart' => $serialsInCart,
            'warnings' => $warnings,
        ];
    }

    protected function getCart(Request $request): array
    {
        return $request->session()->get(self::CART_SESSION_KEY, []);
    }

    protected function saveCart(Request $request, array $cart): void
    {
        $request->session()->put(self::CART_SESSION_KEY, $cart);
    }

    protected function clearCartSession(Request $request): void
    {
        $request->session()->forget(self::CART_SESSION_KEY);
    }

    protected function canInitiateTransfers(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return in_array($user->role, ['super_admin', 'logistics'], true);
    }

    /**
     * @param  array<int, int>  $serialIds
     */
    protected function reserveSerials(StockLocation $warehouse, array $serialIds, int $userId): Collection
    {
        $uniqueIds = array_values(array_unique($serialIds));

        if (empty($uniqueIds)) {
            return collect();
        }

        $now = now();

        return DB::transaction(function () use ($warehouse, $uniqueIds, $userId, $now) {
            $serials = MaterialSerial::query()
                ->whereIn('id', $uniqueIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($serials->count() !== count($uniqueIds)) {
                throw new RuntimeException('Alguno de los numeros de serie seleccionados ya no esta disponible.');
            }

            foreach ($serials as $serial) {
                if ($serial->current_location_id !== $warehouse->id) {
                    throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya no esta disponible.');
                }

                if ($serial->reserved_by_user_id && (int) $serial->reserved_by_user_id !== $userId) {
                    throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya esta reservado por otro usuario.');
                }

                $validStatus = $serial->status === 'available'
                    || ($serial->status === 'reserved' && (int) $serial->reserved_by_user_id === $userId);

                if (! $validStatus) {
                    throw new RuntimeException('El numero de serie ' . $serial->serial_number . ' ya no esta disponible.');
                }
            }

            foreach ($serials as $serial) {
                if ($serial->status === 'reserved' && (int) $serial->reserved_by_user_id === $userId) {
                    continue;
                }

                $serial->update([
                    'status' => 'reserved',
                    'reserved_by_user_id' => $userId,
                    'reserved_at' => $now,
                ]);

                $serial->status = 'reserved';
                $serial->reserved_by_user_id = $userId;
                $serial->reserved_at = $now;
            }

            return $serials;
        });
    }

    /**
     * @param  array<int, int>  $serialIds
     */
    protected function releaseSerialReservations(array $serialIds, int $userId): void
    {
        $uniqueIds = array_values(array_unique($serialIds));

        if (empty($uniqueIds)) {
            return;
        }

        DB::transaction(function () use ($uniqueIds, $userId) {
            $serials = MaterialSerial::query()
                ->whereIn('id', $uniqueIds)
                ->lockForUpdate()
                ->get();

            foreach ($serials as $serial) {
                if ((int) $serial->reserved_by_user_id !== $userId) {
                    continue;
                }

                $serial->update([
                    'status' => $serial->status === 'reserved' ? 'available' : $serial->status,
                    'reserved_by_user_id' => null,
                    'reserved_at' => null,
                ]);
            }
        });
    }

    protected function warehouseLocation(): StockLocation
    {
        $location = StockLocation::warehouses()->first();

        if (! $location) {
            throw new RuntimeException('No se encontro la ubicacion del almacen principal.');
        }

        return $location;
    }

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

    protected function generateTransferNumber(): string
    {
        do {
            $number = 'TRF-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Transfer::where('order_number', $number)->exists());

        return $number;
    }
}
