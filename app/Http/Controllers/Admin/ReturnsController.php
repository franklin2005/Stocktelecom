<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Material;
use App\Models\MaterialSerial;
use App\Models\User;
use App\Services\Admin\AdminReturns\AdminReturnsAuthorizationService;
use App\Services\Admin\AdminReturns\AdminReturnsCartService;
use App\Services\Admin\AdminReturns\AdminReturnsLocationService;
use App\Services\Admin\AdminReturns\AdminReturnsReservationService;
use App\Services\Admin\AdminReturns\AdminReturnsTransferService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ReturnsController extends Controller
{   // constante para clave de sesion del tecnico seleccionado
    private const SELECTED_TECHNICIAN_SESSION_KEY = 'admin_return_selected_technician';

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly AdminReturnsAuthorizationService $authorizationService,
        private readonly AdminReturnsLocationService $locationService,
        private readonly AdminReturnsCartService $cartService,
        private readonly AdminReturnsReservationService $reservationService,
        private readonly AdminReturnsTransferService $transferService,
    ) {
    }
    // Pantalla principal de devoluciones
    public function index(Request $request): View
    {
        $user = $request->user();
        // verificar permisos de acceso
        $this->authorizationService->ensureCanManageReturns($user);
        // obtener lista de tecnicos
        $technicians = User::technicians()
            ->orderBy('name')
            ->get();
        // obtener tecnico seleccionado de query params o sesion
        $selectedTechnicianId = (int) $request->query(
            'technician_id',
            (int) $request->session()->get(self::SELECTED_TECHNICIAN_SESSION_KEY, 0)
        );
        // buscar tecnico seleccionado
        $selectedTechnician = $technicians->firstWhere('id', $selectedTechnicianId) ?: null;
        // guardar tecnico seleccionado en sesion
        if ($selectedTechnician) {
            $request->session()->put(self::SELECTED_TECHNICIAN_SESSION_KEY, $selectedTechnician->id);
        } else {
            $request->session()->forget(self::SELECTED_TECHNICIAN_SESSION_KEY);
        }
        // obtener ubicaciones relevantes
        $warehouse = $this->locationService->warehouseLocation();
        $technicianLocation = $selectedTechnician ? $this->locationService->ensureTechnicianLocation($selectedTechnician) : null;
        // inicializar inventarios y reservas
        $inventory = collect();
        $availableSerials = collect();
        $pendingReservations = [
            'quantities' => [],
            'serial_ids' => [],
        ];
        // cargar inventario y reservas si hay tecnico seleccionado
        if ($technicianLocation) {
            $inventory = Inventory::query()
                ->with('material')
                ->where('location_id', $technicianLocation->id)
                ->orderBy('material_id')
                ->get();
            // obtener numeros de serie disponibles
            $availableSerials = MaterialSerial::query()
                ->with('material')
                ->where('current_location_id', $technicianLocation->id)
                ->where('status', 'assigned')
                ->orderBy('material_id')
                ->orderBy('serial_number')
                ->get();
            // obtener reservas pendientes de devolucion
            $pendingReservations = $this->reservationService->pendingReturnReservations($technicianLocation);
        }
        // preparar datos del carrito
        $cartData = $this->cartService->prepareCart($request, $technicianLocation, $inventory, $availableSerials, $pendingReservations);
        $cartItems = $cartData['items'];
        $cartSummary = $cartData['summary'];
        $reservedQuantities = $cartData['reserved_quantities'];
        $serialsInCart = $cartData['serials_in_cart'];
        // filtrar inventario disponible excluyendo lo ya reservado
        $nonSerializedInventoryFiltered = $inventory
            ->filter(fn ($item) => $item->material && ! $item->material->is_serialized)
            ->filter(function ($item) use ($pendingReservations, $reservedQuantities) {
                $reservedKey  = 'quantity-' . $item->material_id;
                $pending      = $pendingReservations['quantities'][$reservedKey] ?? 0;
                $cartReserved = $reservedQuantities[$reservedKey] ?? 0;
                $available    = max(($item->quantity ?? 0) - $pending - $cartReserved, 0);
                return $available > 0;
            })
            ->values();
            // filtrar numeros de serie disponibles excluyendo los ya reservados
        $availableSerialsFiltered = $availableSerials
            ->reject(function ($serial) use ($pendingReservations, $serialsInCart) {
                return isset($pendingReservations['serial_ids'][$serial->id])
                    || in_array($serial->id, $serialsInCart, true);
            })
            ->values();
            // retornar vista con datos
        return view('admin.returns', [
            'warehouse' => $warehouse,
            'technicians' => $technicians,
            'selectedTechnician' => $selectedTechnician,
            'technicianLocation' => $technicianLocation,
            'nonSerializedInventory' => $nonSerializedInventoryFiltered,
            'serializedInventory' => $inventory
                ->filter(fn ($item) => $item->material && $item->material->is_serialized)
                ->values()
                ->keyBy('material_id'),
            'availableSerials' => $availableSerialsFiltered,
            'pendingReservations' => $pendingReservations,
            'cartItems' => $cartItems,
            'cartSummary' => $cartSummary,
            'reservedQuantities' => $reservedQuantities,
            'serialsInCart' => $serialsInCart,
        ]);
    }
    // Agregar material al carrito de devolucion
    public function addToCart(Request $request): RedirectResponse
    {   // verificar permisos de acceso
        $user = $request->user();
        $this->authorizationService->ensureCanManageReturns($user);
        // obtener tecnico seleccionado
        $technicianId = (int) $request->input('technician_id', 0);
        $technician = User::technicians()->whereKey($technicianId)->first();
        // si no es valido, error
        if (! $technician) {
            return back()->withErrors(['technician_id' => 'Selecciona un t?cnico volido para solicitar la devoluci??n.'])->withInput();
        }
        // obtener ubicacion del tecnico
        $technicianLocation = $this->locationService->ensureTechnicianLocation($technician);
        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $technicianLocation->id)
            ->get();
        // obtener reservas pendientes
        $pendingReservations = $this->reservationService->pendingReturnReservations($technicianLocation);
        $cart = $this->cartService->getCart($request);
        // determinar tipo de adicion
        $intent = $request->input('intent');
        // procesar segun tipo
        if (! in_array($intent, ['quantity', 'serial'], true)) {
            return back()->withErrors(['cart' => 'No se pudo determinar la acci??n solicitada.']);
        }
        // agregar por cantidad
        if ($intent === 'quantity') {
            $validated = $request->validate([
                'material_id' => ['required', 'integer'],
                'quantity' => ['required', 'integer', 'min:1'],
            ]);
            // verificar disponibilidad
            $material = $inventory->firstWhere('material_id', (int) $validated['material_id']);
            // si no es valido, error
            if (! $material || ! $material->material || $material->material->is_serialized) {
                return back()->withErrors(['material_id' => 'El material seleccionado no esto disponible en el inventario del t?cnico.']);
            }
            // calcular disponibilidad real
            $reservedKey = 'quantity-' . $material->material_id;
            $alreadyReserved = $pendingReservations['quantities'][$reservedKey] ?? 0;
            $cartReserved = $cart[$reservedKey]['quantity'] ?? 0;
            $available = max(($material->quantity ?? 0) - $alreadyReserved - $cartReserved, 0);
            $requested = (int) $validated['quantity'];
            // si no hay suficiente, error
            if ($requested > $available) {
                return back()->withErrors([
                    'quantity' => 'Solo hay ' . $available . ' unidades disponibles para solicitar.',
                ])->withInput();
            }
            // agregar al carrito
            $cart[$reservedKey] = [
                'type' => 'quantity',
                'material_id' => $material->material_id,
                'quantity' => $cartReserved + $requested,
                'technician_id' => $technician->id,
            ];
            // guardar carrito actualizado
            $this->cartService->saveCart($request, $cart);
            // retornar con exito
            return back()->with('status', 'Se agregaron ' . $requested . ' unidades a la solicitud.');
        }
        // agregar por numeros de serie
        $validated = $request->validate([
            'serial_ids' => ['required', 'array', 'min:1'],
            'serial_ids.*' => ['integer'],
        ], [
            'serial_ids.required' => 'Selecciona al menos un ngmero de serie.',
        ]);
        // procesar ids unicos
        $serialIds = collect($validated['serial_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        // si no hay ids validos, error
        if (empty($serialIds)) {
            return back()->withErrors(['serial_ids' => 'Selecciona al menos un ngmero de serie volido.']);
        }
        // obtener numeros de serie y verificar disponibilidad
        $serials = MaterialSerial::query()
            ->with('material')
            ->whereIn('id', $serialIds)
            ->where('current_location_id', $technicianLocation->id)
            ->where('status', 'assigned')
            ->get()
            ->keyBy('id');
        // si alguno no es valido, error
        if ($serials->count() !== count($serialIds)) {
            return back()->withErrors(['serial_ids' => 'Alguno de los ngmeros de serie seleccionados ya no esto disponible.']);
        }
        // verificar que no esten reservados
        foreach ($serials as $serial) {
            if (($pendingReservations['serial_ids'][$serial->id] ?? false) || isset($cart['serial-' . $serial->id])) {
                return back()->withErrors([
                    'serial_ids' => 'El ngmero de serie ' . $serial->serial_number . ' ya esto reservado en otra solicitud.',
                ]);
            }
        }
        // agregar al carrito
        foreach ($serials as $serial) {
            $cart['serial-' . $serial->id] = [
                'type' => 'serial',
                'material_id' => $serial->material_id,
                'serial_id' => $serial->id,
                'technician_id' => $technician->id,
            ];
        }
        // guardar carrito actualizado
        $this->cartService->saveCart($request, $cart);
        // retornar con exito
        return back()->with('status', 'Se a??adieron ' . $serials->count() . ' ngmeros de serie a la solicitud.');
    }
    // Quitar material del carrito de devolucion
    public function removeFromCart(Request $request, string $key): RedirectResponse
    {   // verificar permisos de acceso
        $user = $request->user();
        $this->authorizationService->ensureCanManageReturns($user);
        // obtener carrito
        $cart = $this->cartService->getCart($request);
        // verificar si el elemento existe en el carrito
        if (isset($cart[$key])) {
            unset($cart[$key]);
            $this->cartService->saveCart($request, $cart);
            // retornar con exito
            return back()->with('status', 'Elemento quitado de la lista de devoluci??n.');
        }
        // si no existe, error
        return back()->withErrors(['cart' => 'El elemento seleccionado ya no estaba en la lista.']);
    }
    // Vaciar el carrito de devolucion
    public function clearCart(Request $request): RedirectResponse
    {   // verificar permisos de acceso
        $user = $request->user();
        $this->authorizationService->ensureCanManageReturns($user);
        // vaciar carrito
        $this->cartService->clearCart($request);
// retornar con exito
        return back()->with('status', 'Se vaci?? la lista de devoluci??n.');
    }
    // Enviar la solicitud de devolucion
    public function send(Request $request): RedirectResponse
    {   // verificar permisos de acceso
        $user = $request->user();
        $this->authorizationService->ensureCanManageReturns($user);
        // obtener carrito
        $cart = $this->cartService->getCart($request);
        // si el carrito esta vacio, error
        if (empty($cart)) {
            return back()->withErrors(['cart' => 'Agrega materiales antes de generar la devoluci??n.']);
        }
        // determinar tecnico de la solicitud
        $technicianId = collect($cart)->first()['technician_id'] ?? null;
        $technician = $technicianId ? User::technicians()->whereKey($technicianId)->first() : null;
        // si no es valido, error
        if (! $technician) {
            return back()->withErrors(['technician_id' => 'No se pudo determinar el t?cnico para la solicitud.']);
        }
        // obtener ubicaciones
        $technicianLocation = $this->locationService->ensureTechnicianLocation($technician);
        $warehouse = $this->locationService->warehouseLocation();
        // obtener inventario y reservas
        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $technicianLocation->id)
            ->get();
        // obtener reservas pendientes
        $pendingReservations = $this->reservationService->pendingReturnReservations($technicianLocation);
        // normalizar items del carrito
        $items = $this->cartService->normalizeCartItems($cart);
        // intentar crear la transferencia de devolucion
        try {
            $this->transferService->createReturnTransfer($user, $technicianLocation, $warehouse, $items, $inventory, $pendingReservations);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['cart' => $exception->getMessage()]);
        }
        // limpiar carrito
        $this->cartService->clearCart($request);
        // redirigir con exito
        return redirect()
            ->route('admin.returns', ['technician_id' => $technician->id])
            ->with('status', 'Solicitud de devoluci??n registrada correctamente.');
    }
}
