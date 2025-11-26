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
{
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

    public function index(Request $request): View
    {
        $user = $request->user();

        $this->authorizationService->ensureCanManageReturns($user);

        $technicians = User::technicians()
            ->orderBy('name')
            ->get();

        $selectedTechnicianId = (int) $request->query(
            'technician_id',
            (int) $request->session()->get(self::SELECTED_TECHNICIAN_SESSION_KEY, 0)
        );

        $selectedTechnician = $technicians->firstWhere('id', $selectedTechnicianId) ?: null;

        if ($selectedTechnician) {
            $request->session()->put(self::SELECTED_TECHNICIAN_SESSION_KEY, $selectedTechnician->id);
        } else {
            $request->session()->forget(self::SELECTED_TECHNICIAN_SESSION_KEY);
        }

        $warehouse = $this->locationService->warehouseLocation();
        $technicianLocation = $selectedTechnician ? $this->locationService->ensureTechnicianLocation($selectedTechnician) : null;

        $inventory = collect();
        $availableSerials = collect();
        $pendingReservations = [
            'quantities' => [],
            'serial_ids' => [],
        ];

        if ($technicianLocation) {
            $inventory = Inventory::query()
                ->with('material')
                ->where('location_id', $technicianLocation->id)
                ->orderBy('material_id')
                ->get();

            $availableSerials = MaterialSerial::query()
                ->with('material')
                ->where('current_location_id', $technicianLocation->id)
                ->where('status', 'assigned')
                ->orderBy('material_id')
                ->orderBy('serial_number')
                ->get();

            $pendingReservations = $this->reservationService->pendingReturnReservations($technicianLocation);
        }

        $cartData = $this->cartService->prepareCart($request, $technicianLocation, $inventory, $availableSerials, $pendingReservations);
        $cartItems = $cartData['items'];
        $cartSummary = $cartData['summary'];
        $reservedQuantities = $cartData['reserved_quantities'];
        $serialsInCart = $cartData['serials_in_cart'];

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

        $availableSerialsFiltered = $availableSerials
            ->reject(function ($serial) use ($pendingReservations, $serialsInCart) {
                return isset($pendingReservations['serial_ids'][$serial->id])
                    || in_array($serial->id, $serialsInCart, true);
            })
            ->values();

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

    public function addToCart(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorizationService->ensureCanManageReturns($user);

        $technicianId = (int) $request->input('technician_id', 0);
        $technician = User::technicians()->whereKey($technicianId)->first();

        if (! $technician) {
            return back()->withErrors(['technician_id' => 'Selecciona un t?cnico volido para solicitar la devoluci??n.'])->withInput();
        }

        $technicianLocation = $this->locationService->ensureTechnicianLocation($technician);
        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $technicianLocation->id)
            ->get();

        $pendingReservations = $this->reservationService->pendingReturnReservations($technicianLocation);
        $cart = $this->cartService->getCart($request);

        $intent = $request->input('intent');

        if (! in_array($intent, ['quantity', 'serial'], true)) {
            return back()->withErrors(['cart' => 'No se pudo determinar la acci??n solicitada.']);
        }

        if ($intent === 'quantity') {
            $validated = $request->validate([
                'material_id' => ['required', 'integer'],
                'quantity' => ['required', 'integer', 'min:1'],
            ]);

            $material = $inventory->firstWhere('material_id', (int) $validated['material_id']);

            if (! $material || ! $material->material || $material->material->is_serialized) {
                return back()->withErrors(['material_id' => 'El material seleccionado no esto disponible en el inventario del t?cnico.']);
            }

            $reservedKey = 'quantity-' . $material->material_id;
            $alreadyReserved = $pendingReservations['quantities'][$reservedKey] ?? 0;
            $cartReserved = $cart[$reservedKey]['quantity'] ?? 0;
            $available = max(($material->quantity ?? 0) - $alreadyReserved - $cartReserved, 0);
            $requested = (int) $validated['quantity'];

            if ($requested > $available) {
                return back()->withErrors([
                    'quantity' => 'Solo hay ' . $available . ' unidades disponibles para solicitar.',
                ])->withInput();
            }

            $cart[$reservedKey] = [
                'type' => 'quantity',
                'material_id' => $material->material_id,
                'quantity' => $cartReserved + $requested,
                'technician_id' => $technician->id,
            ];

            $this->cartService->saveCart($request, $cart);

            return back()->with('status', 'Se agregaron ' . $requested . ' unidades a la solicitud.');
        }

        $validated = $request->validate([
            'serial_ids' => ['required', 'array', 'min:1'],
            'serial_ids.*' => ['integer'],
        ], [
            'serial_ids.required' => 'Selecciona al menos un ngmero de serie.',
        ]);

        $serialIds = collect($validated['serial_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($serialIds)) {
            return back()->withErrors(['serial_ids' => 'Selecciona al menos un ngmero de serie volido.']);
        }

        $serials = MaterialSerial::query()
            ->with('material')
            ->whereIn('id', $serialIds)
            ->where('current_location_id', $technicianLocation->id)
            ->where('status', 'assigned')
            ->get()
            ->keyBy('id');

        if ($serials->count() !== count($serialIds)) {
            return back()->withErrors(['serial_ids' => 'Alguno de los ngmeros de serie seleccionados ya no esto disponible.']);
        }

        foreach ($serials as $serial) {
            if (($pendingReservations['serial_ids'][$serial->id] ?? false) || isset($cart['serial-' . $serial->id])) {
                return back()->withErrors([
                    'serial_ids' => 'El ngmero de serie ' . $serial->serial_number . ' ya esto reservado en otra solicitud.',
                ]);
            }
        }

        foreach ($serials as $serial) {
            $cart['serial-' . $serial->id] = [
                'type' => 'serial',
                'material_id' => $serial->material_id,
                'serial_id' => $serial->id,
                'technician_id' => $technician->id,
            ];
        }

        $this->cartService->saveCart($request, $cart);

        return back()->with('status', 'Se a??adieron ' . $serials->count() . ' ngmeros de serie a la solicitud.');
    }

    public function removeFromCart(Request $request, string $key): RedirectResponse
    {
        $cart = $this->cartService->getCart($request);

        if (isset($cart[$key])) {
            unset($cart[$key]);
            $this->cartService->saveCart($request, $cart);

            return back()->with('status', 'Elemento quitado de la lista de devoluci??n.');
        }

        return back()->withErrors(['cart' => 'El elemento seleccionado ya no estaba en la lista.']);
    }

    public function clearCart(Request $request): RedirectResponse
    {
        $this->cartService->clearCart($request);

        return back()->with('status', 'Se vaci?? la lista de devoluci??n.');
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorizationService->ensureCanManageReturns($user);

        $cart = $this->cartService->getCart($request);

        if (empty($cart)) {
            return back()->withErrors(['cart' => 'Agrega materiales antes de generar la devoluci??n.']);
        }

        $technicianId = collect($cart)->first()['technician_id'] ?? null;
        $technician = $technicianId ? User::technicians()->whereKey($technicianId)->first() : null;

        if (! $technician) {
            return back()->withErrors(['technician_id' => 'No se pudo determinar el t?cnico para la solicitud.']);
        }

        $technicianLocation = $this->locationService->ensureTechnicianLocation($technician);
        $warehouse = $this->locationService->warehouseLocation();

        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $technicianLocation->id)
            ->get();

        $pendingReservations = $this->reservationService->pendingReturnReservations($technicianLocation);

        $items = $this->cartService->normalizeCartItems($cart);

        try {
            $this->transferService->createReturnTransfer($user, $technicianLocation, $warehouse, $items, $inventory, $pendingReservations);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['cart' => $exception->getMessage()]);
        }

        $this->cartService->clearCart($request);

        return redirect()
            ->route('admin.returns', ['technician_id' => $technician->id])
            ->with('status', 'Solicitud de devoluci??n registrada correctamente.');
    }
}
