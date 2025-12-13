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
        // chequeo de permisos
        if (! $this->locationService->canInitiateTransfers($user)) {
            abort(403);
        }
        // get de ubicacion del almacen
        $warehouse = $this->locationService->warehouseLocation();
        // obtener inventario del almacen
        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $warehouse->id)
            ->orderBy('material_id')
            ->get();
        // numeros de serie disponibles en el almacen
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
            // preparar datos del carrito
        $cartData = $this->cartService->prepareCart($request, $warehouse, $inventory, $availableSerials, (int) $user->id);
        $cartItems = $cartData['items'];
        $cartSummary = $cartData['summary'];
        $reservedQuantities = $cartData['reserved_quantities'];
        $serialsInCart = $cartData['serials_in_cart'];
            // mostrar advertencias del carrito
        if (! empty($cartData['warnings'])) {
            $request->session()->flash('cart_warning', implode(' ', $cartData['warnings']));
        }
        // busqueda de tecnicos
        $recipientSearch = trim((string) $request->query('recipient_search', ''));
        $recipientQuery = User::technicians()->orderBy('name');
        // aplicar filtro de busqueda si existe
        if ($recipientSearch !== '') {
            $recipientQuery->where('name', 'like', '%' . $recipientSearch . '%');
        }
        // limitar resultados a 25 tecnicos
        $technicians = $recipientQuery->limit(25)->get();
        // separar inventario en serializados y no serializados
        $nonSerializedInventory = $inventory->filter(fn ($item) => $item->material && ! $item->material->is_serialized)->values();
        $serializedInventory = $inventory->filter(fn ($item) => $item->material && $item->material->is_serialized)->values()->keyBy('material_id');
        // renderizar vista con datos
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
     * añadir un item al carrito de transferencia.
     */
    public function addToCart(Request $request): RedirectResponse
    {   // obtener usuario actual
        $user = $request->user();
        // chequeo de permisos
        if (! $this->locationService->canInitiateTransfers($user)) {
            abort(403);
        }
        // obtener inventario del almacen
        $warehouse = $this->locationService->warehouseLocation();
        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $warehouse->id)
            ->get();
        // determinar intencion (cantidad o serial)
        $intent = $request->input('intent');
        
        if (! in_array($intent, ['quantity', 'serial'], true)) {
            return back()->withErrors(['cart' => 'No se pudo determinar la accion solicitada.']);
        }
        // obtener carrito actual
        $cart = $this->cartService->getCart($request);
        // manejar adicion por cantidad
        if ($intent === 'quantity') {
            $validated = $request->validate([
                'material_id' => ['required', 'integer'],
                'quantity' => ['required', 'integer', 'min:1'],
            ]);
            // verificar que el material exista y no sea serializado
            $material = Material::query()
                ->whereKey($validated['material_id'])
                ->where('is_serialized', false)
                ->first();
            // si no existe, error
            if (! $material) {
                return back()->withErrors(['material_id' => 'El material seleccionado no esta disponible.']);
            }
            // verificar disponibilidad en inventario
            $available = $inventory->firstWhere('material_id', $material->id)?->quantity ?? 0;
            $reserved = $cart['quantity-' . $material->id]['quantity'] ?? 0;
            $requested = (int) $validated['quantity'];
            // si no hay stock, error
            if ($available <= 0) {
                return back()->withErrors(['material_id' => 'No hay stock disponible de este material en el almacen.']);
            }
            // si se excede stock, error
            if ($requested + $reserved > $available) {
                return back()->withErrors(['quantity' => 'Solo hay ' . max($available - $reserved, 0) . ' unidades disponibles.']);
            }
            // agregar al carrito
            $cart['quantity-' . $material->id] = [
                'type' => 'quantity',
                'material_id' => $material->id,
                'quantity' => $requested + $reserved,
            ];
            // guardar carrito actualizado
            $this->cartService->saveCart($request, $cart);
            // confirmar adicion
            return back()->with('status', 'Se agregaron ' . $requested . ' unidades de ' . ucfirst($material->type) . ' a la lista.');
        }
        // manejar adicion por numeros de serie
        $validated = $request->validate([
            'serial_ids' => ['required', 'array', 'min:1'],
            'serial_ids.*' => ['integer'],
        ]);
        // verificar que los numeros de serie existan y esten disponibles
        $serialIds = collect($validated['serial_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        // buscar duplicados en el carrito
        $duplicates = $serialIds->filter(fn ($id) => isset($cart['serial-' . $id]));
        // si hay duplicados, error
        if ($duplicates->isNotEmpty()) {
            return back()->withErrors(['serial_ids' => 'Los numeros de serie ' . $duplicates->implode(', ') . ' ya estan en la lista.']);
        }
        // reservar los numeros de serie
        try {
            $reservedSerials = $this->serialService->reserveSerials($warehouse, $serialIds->all(), (int) $user->id);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['serial_ids' => $exception->getMessage()]);
        }
        // agregar numeros de serie reservados al carrito
        foreach ($reservedSerials as $serial) {
            $cart['serial-' . $serial->id] = [
                'type' => 'serial',
                'material_id' => $serial->material_id,
                'serial_id' => $serial->id,
            ];
        }
        // guardar carrito actualizado
        $this->cartService->saveCart($request, $cart);
        // confirmar adicion
        return back()->with('status', 'Se agregaron ' . $reservedSerials->count() . ' numeros de serie a la lista.');
    }

    /**
     * eliminar un item del carrito de transferencia.
     */
    public function removeFromCart(Request $request, string $itemKey): RedirectResponse
    {
        $user = $request->user();

        if (! $this->locationService->canInitiateTransfers($user)) {
            abort(403);
        }
        // obtener carrito actual
        $cart = $this->cartService->getCart($request);
        // verificar que el item exista en el carrito
        if (! isset($cart[$itemKey])) {
            return back()->withErrors(['cart' => 'El elemento seleccionado ya no estaba en la lista.']);
        }
        // obtener el item
        $item = $cart[$itemKey];
        // si es un numero de serie, liberar la reserva
        if (($item['type'] ?? null) === 'serial' && isset($item['serial_id'])) {
            $this->serialService->releaseSerialReservations([(int) $item['serial_id']], (int) $user->id);
        }
        // eliminar el item del carrito
        unset($cart[$itemKey]);
        $this->cartService->saveCart($request, $cart);
        
        return back()->with('status', 'Elemento retirado de la lista.');
    }

    /**
     * vaciar el carrito de transferencia.
     */
    public function clearCart(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->locationService->canInitiateTransfers($user)) {
            abort(403);
        }
        // obtener carrito actual
        $cart = $this->cartService->getCart($request);
        // obtener ids de numeros de serie en el carrito
        $serialIds = collect($cart)
            ->filter(fn ($item) => is_array($item) && ($item['type'] ?? null) === 'serial' && isset($item['serial_id']))
            ->map(fn ($item) => (int) $item['serial_id'])
            ->values()
            ->all();
        // liberar reservas de numeros de serie
        if (! empty($serialIds)) {
            $this->serialService->releaseSerialReservations($serialIds, (int) $user->id);
        }
        // limpiar carrito
        $this->cartService->clearCartSession($request);
        // confirmar vaciado
        return back()->with('status', 'Se vacio la lista de transferencia.');
    }

    /**
     * enviar transferencia al tecnico seleccionado.
     */
    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();
        // chequeo de permisos
        if (! $this->locationService->canInitiateTransfers($user)) {
            abort(403);
        }
        // obtener inventario del almacen
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
            // preparar datos del carrito
        $cartData = $this->cartService->prepareCart($request, $warehouse, $inventory, $availableSerials, (int) $user->id);
        $cartItems = $cartData['items'];
        $cartSummary = $cartData['summary'];
            // manejar advertencias del carrito
        if (! empty($cartData['warnings'])) {
            return back()->withErrors(['cart' => implode(' ', $cartData['warnings'])]);
        }
        // verificar que el carrito no este vacio
        if (empty($cartItems)) {
            return back()->withErrors(['cart' => 'Agrega materiales antes de generar una transferencia.']);
        }
        // validar tecnico destinatario
        $technician = User::technicians()->whereKey((int) $request->input('technician_id'))->first();
        // si no es valido, error
        if (! $technician) {
            return back()->withErrors(['technician_id' => 'Selecciona un tecnico valido.']);
        }
        // asegurar que el tecnico tenga una ubicacion asignada
        $technicianLocation = $this->locationService->ensureTechnicianLocation($technician);
        $userId = (int) $user->id;
        // preparar materiales y numeros de serie para la transferencia
        $materialIds = collect($cartItems)->pluck('material_id')->filter()->unique()->values();
        $materials = Material::query()->whereIn('id', $materialIds)->get()->keyBy('id');
        // preparar ids de numeros de serie
        $serialIds = collect($cartItems)
            ->where('type', 'serial')
            ->pluck('serial_id')
            ->filter()
            ->unique()
            ->values();
        // crear la transferencia
        try {
            $this->creationService->createTransfer($warehouse, $technicianLocation, $userId, $materials, $serialIds, $cartItems);
        } catch (RuntimeException $exception) { 
            return back()->withErrors(['cart' => $exception->getMessage()]);
        }
        // limpiar el carrito
        $this->cartService->clearCartSession($request);
        // confirmar creacion de transferencia
        return redirect()->route('admin.transfers')
            ->with('status', 'Transferencia generada: ' . $cartSummary['total_units'] . ' elementos enviados.');
    }
}
