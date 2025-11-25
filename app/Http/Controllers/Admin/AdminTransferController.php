<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Material;
use App\Models\MaterialSerial;
use App\Models\User;
use App\Services\Admin\AdminTransfer\AdminTransferCartService;
use App\Services\Admin\AdminTransfer\AdminTransferCreationService;
use App\Services\Admin\AdminTransfer\AdminTransferLocationService;
use App\Services\Admin\AdminTransfer\AdminTransferSerialService;
use App\Services\InventoryService;
use App\Services\StockMovementLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AdminTransferController extends Controller
{
    public function __construct(
        private readonly AdminTransferCartService $cartService,
        private readonly AdminTransferSerialService $serialService,
        private readonly AdminTransferLocationService $locationService,
        private readonly AdminTransferCreationService $creationService,
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

        if (! $this->locationService->canInitiateTransfers($user)) {
            abort(403);
        }

        $warehouse = $this->locationService->warehouseLocation();

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

        $cartData = $this->cartService->prepareCart($request, $warehouse, $inventory, $availableSerials, (int) $user->id);
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

        if (! $this->locationService->canInitiateTransfers($user)) {
            abort(403);
        }

        $warehouse = $this->locationService->warehouseLocation();
        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $warehouse->id)
            ->get();

        $intent = $request->input('intent');

        if (! in_array($intent, ['quantity', 'serial'], true)) {
            return back()->withErrors(['cart' => 'No se pudo determinar la accion solicitada.']);
        }

        $cart = $this->cartService->getCart($request);

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

            $this->cartService->saveCart($request, $cart);

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
            $reservedSerials = $this->serialService->reserveSerials($warehouse, $serialIds->all(), (int) $user->id);
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

        $this->cartService->saveCart($request, $cart);

        return back()->with('status', 'Se agregaron ' . $reservedSerials->count() . ' numeros de serie a la lista.');
    }

    /**
     * Remove an item from the cart.
     */
    public function removeFromCart(Request $request, string $itemKey): RedirectResponse
    {
        $user = $request->user();

        if (! $this->locationService->canInitiateTransfers($user)) {
            abort(403);
        }

        $cart = $this->cartService->getCart($request);

        if (! isset($cart[$itemKey])) {
            return back()->withErrors(['cart' => 'El elemento seleccionado ya no estaba en la lista.']);
        }

        $item = $cart[$itemKey];

        if (($item['type'] ?? null) === 'serial' && isset($item['serial_id'])) {
            $this->serialService->releaseSerialReservations([(int) $item['serial_id']], (int) $user->id);
        }

        unset($cart[$itemKey]);
        $this->cartService->saveCart($request, $cart);

        return back()->with('status', 'Elemento retirado de la lista.');
    }

    /**
     * Clear cart entirely.
     */
    public function clearCart(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->locationService->canInitiateTransfers($user)) {
            abort(403);
        }

        $cart = $this->cartService->getCart($request);

        $serialIds = collect($cart)
            ->filter(fn ($item) => is_array($item) && ($item['type'] ?? null) === 'serial' && isset($item['serial_id']))
            ->map(fn ($item) => (int) $item['serial_id'])
            ->values()
            ->all();

        if (! empty($serialIds)) {
            $this->serialService->releaseSerialReservations($serialIds, (int) $user->id);
        }

        $this->cartService->clearCartSession($request);

        return back()->with('status', 'Se vacio la lista de transferencia.');
    }

    /**
     * Generate transfer from warehouse to technician.
     */
    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->locationService->canInitiateTransfers($user)) {
            abort(403);
        }

        $warehouse = $this->locationService->warehouseLocation();
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

        $cartData = $this->cartService->prepareCart($request, $warehouse, $inventory, $availableSerials, (int) $user->id);
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

        $technicianLocation = $this->locationService->ensureTechnicianLocation($technician);
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
            $this->creationService->createTransfer($warehouse, $technicianLocation, $userId, $materials, $serialIds, $cartItems);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['cart' => $exception->getMessage()]);
        }

        $this->cartService->clearCartSession($request);

        return redirect()->route('admin.transfers')
            ->with('status', 'Transferencia generada: ' . $cartSummary['total_units'] . ' elementos enviados.');
    }
}
