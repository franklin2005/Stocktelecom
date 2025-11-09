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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class ReturnsController extends Controller
{
    private const CART_SESSION_KEY = 'admin_return_cart';
    private const SELECTED_TECHNICIAN_SESSION_KEY = 'admin_return_selected_technician';

    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {
    }

    public function index(Request $request): View
{
    $user = $request->user();

    $this->ensureCanManageReturns($user);

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

    $warehouse = $this->warehouseLocation();
    $technicianLocation = $selectedTechnician ? $this->ensureTechnicianLocation($selectedTechnician) : null;

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

        $pendingReservations = $this->pendingReturnReservations($technicianLocation);
    }

    $cartData = $this->prepareCart($request, $technicianLocation, $inventory, $availableSerials, $pendingReservations);
    $cartItems = $cartData['items'];
    $cartSummary = $cartData['summary'];
    $reservedQuantities = $cartData['reserved_quantities'];
    $serialsInCart = $cartData['serials_in_cart'];

    // Filtrar no-serializados para mostrar solo los que realmente tienen disponibilidad (>0)
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

    // Ocultar seriales que ya están reservados en otra solicitud o añadidos al carrito
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

        // Usar colección filtrada:
        'nonSerializedInventory' => $nonSerializedInventoryFiltered,

        // Mantener agrupado por material para totales/etiquetas en la vista:
        'serializedInventory' => $inventory
            ->filter(fn ($item) => $item->material && $item->material->is_serialized)
            ->values()
            ->keyBy('material_id'),

        // Usar seriales filtrados:
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
        $this->ensureCanManageReturns($user);

        $technicianId = (int) $request->input('technician_id', 0);
        $technician = User::technicians()->whereKey($technicianId)->first();

        if (! $technician) {
            return back()->withErrors(['technician_id' => 'Selecciona un técnico válido para solicitar la devolución.'])->withInput();
        }

        $technicianLocation = $this->ensureTechnicianLocation($technician);
        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $technicianLocation->id)
            ->get();

        $pendingReservations = $this->pendingReturnReservations($technicianLocation);
        $cart = $this->getCart($request);

        $intent = $request->input('intent');

        if (! in_array($intent, ['quantity', 'serial'], true)) {
            return back()->withErrors(['cart' => 'No se pudo determinar la acción solicitada.']);
        }

        if ($intent === 'quantity') {
            $validated = $request->validate([
                'material_id' => ['required', 'integer'],
                'quantity' => ['required', 'integer', 'min:1'],
            ]);

            $material = $inventory->firstWhere('material_id', (int) $validated['material_id']);

            if (! $material || ! $material->material || $material->material->is_serialized) {
                return back()->withErrors(['material_id' => 'El material seleccionado no está disponible en el inventario del técnico.']);
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

            $this->saveCart($request, $cart);

            return back()->with('status', 'Se agregaron ' . $requested . ' unidades a la solicitud.');
        }

        $validated = $request->validate([
            'serial_ids' => ['required', 'array', 'min:1'],
            'serial_ids.*' => ['integer'],
        ], [
            'serial_ids.required' => 'Selecciona al menos un número de serie.',
        ]);

        $serialIds = collect($validated['serial_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($serialIds)) {
            return back()->withErrors(['serial_ids' => 'Selecciona al menos un número de serie válido.']);
        }

        $serials = MaterialSerial::query()
            ->with('material')
            ->whereIn('id', $serialIds)
            ->where('current_location_id', $technicianLocation->id)
            ->where('status', 'assigned')
            ->get()
            ->keyBy('id');

        if ($serials->count() !== count($serialIds)) {
            return back()->withErrors(['serial_ids' => 'Alguno de los números de serie seleccionados ya no está disponible.']);
        }

        foreach ($serials as $serial) {
            if (($pendingReservations['serial_ids'][$serial->id] ?? false) || isset($cart['serial-' . $serial->id])) {
                return back()->withErrors([
                    'serial_ids' => 'El número de serie ' . $serial->serial_number . ' ya está reservado en otra solicitud.',
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

        $this->saveCart($request, $cart);

        return back()->with('status', 'Se añadieron ' . $serials->count() . ' números de serie a la solicitud.');
    }

    public function removeFromCart(Request $request, string $key): RedirectResponse
    {
        $cart = $this->getCart($request);

        if (isset($cart[$key])) {
            unset($cart[$key]);
            $this->saveCart($request, $cart);

            return back()->with('status', 'Elemento quitado de la lista de devolución.');
        }

        return back()->withErrors(['cart' => 'El elemento seleccionado ya no estaba en la lista.']);
    }

    public function clearCart(Request $request): RedirectResponse
    {
        $this->clearCartSession($request);

        return back()->with('status', 'Se vació la lista de devolución.');
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->ensureCanManageReturns($user);

        $cart = $this->getCart($request);

        if (empty($cart)) {
            return back()->withErrors(['cart' => 'Agrega materiales antes de generar la devolución.']);
        }

        $technicianId = collect($cart)->first()['technician_id'] ?? null;
        $technician = $technicianId ? User::technicians()->whereKey($technicianId)->first() : null;

        if (! $technician) {
            return back()->withErrors(['technician_id' => 'No se pudo determinar el técnico para la solicitud.']);
        }

        $technicianLocation = $this->ensureTechnicianLocation($technician);
        $warehouse = $this->warehouseLocation();

        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $technicianLocation->id)
            ->get();

        $pendingReservations = $this->pendingReturnReservations($technicianLocation);

        $items = $this->normalizeCartItems($cart);

        try {
            DB::transaction(function () use ($items, $technicianLocation, $warehouse, $user, $inventory, $pendingReservations) {
                $materialIds = collect($items)->pluck('material_id')->unique()->values();
                $materials = Material::query()->whereIn('id', $materialIds)->get()->keyBy('id');

                $transfer = Transfer::create([
                    'order_number' => $this->generateTransferNumber(),
                    'type' => 'return',
                    'from_location_id' => $technicianLocation->id,
                    'to_location_id' => $warehouse->id,
                    'initiator_user_id' => $user->id,
                    'requires_receiver_accept' => true,
                    'status' => 'pending',
                    'notes' => null,
                ]);

                foreach ($items as $item) {
                    $material = $materials->get($item['material_id']);

                    if (! $material) {
                        throw new RuntimeException('No se encontró información del material seleccionado.');
                    }

                    if ($item['type'] === 'quantity') {
                        $inventoryItem = $inventory->firstWhere('material_id', $material->id);
                        $reservedKey = 'quantity-' . $material->id;
                        $alreadyReserved = $pendingReservations['quantities'][$reservedKey] ?? 0;
                        $available = max(($inventoryItem->quantity ?? 0) - $alreadyReserved, 0);

                        if ($item['quantity'] > $available) {
                            throw new RuntimeException('El material ' . ucfirst($material->type) . ' ya no cuenta con la disponibilidad solicitada.');
                        }

                        TransferItem::create([
                            'transfer_id' => $transfer->id,
                            'material_id' => $material->id,
                            'quantity' => $item['quantity'],
                        ]);
                    } else {
                        $serial = MaterialSerial::query()
                            ->whereKey($item['serial_id'])
                            ->where('current_location_id', $technicianLocation->id)
                            ->where('status', 'assigned')
                            ->first();

                        if (! $serial) {
                            throw new RuntimeException('Alguno de los números de serie ya no está disponible.');
                        }

                        TransferItem::create([
                            'transfer_id' => $transfer->id,
                            'material_id' => $material->id,
                            'material_serial_id' => $serial->id,
                        ]);
                    }
                }
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors(['cart' => $exception->getMessage()]);
        }

        $this->clearCartSession($request);

        return redirect()
            ->route('admin.returns', ['technician_id' => $technician->id])
            ->with('status', 'Solicitud de devolución registrada correctamente.');
    }

    protected function prepareCart(
        Request $request,
        ?StockLocation $technicianLocation,
        Collection $inventory,
        Collection $serials,
        array $pendingReservations
    ): array {
        $cart = $this->getCart($request);
        $items = [];
        $summary = [
            'total_items' => 0,
            'total_units' => 0,
        ];
        $reservedQuantities = [];
        $serialsInCart = [];
        $dirty = false;

        foreach ($cart as $key => $entry) {
            if (! is_array($entry) || ! isset($entry['type'], $entry['material_id'])) {
                unset($cart[$key]);
                $dirty = true;
                continue;
            }

            if ($technicianLocation && (int) ($entry['technician_id'] ?? 0) !== $technicianLocation->ref_id) {
                unset($cart[$key]);
                $dirty = true;
                continue;
            }

            $materialId = (int) $entry['material_id'];
            $material = Material::find($materialId);

            if (! $material) {
                unset($cart[$key]);
                $dirty = true;
                continue;
            }

            if ($entry['type'] === 'quantity') {
                $quantity = (int) ($entry['quantity'] ?? 0);

                if ($quantity < 1) {
                    unset($cart[$key]);
                    $dirty = true;
                    continue;
                }

                $reservedKey = 'quantity-' . $materialId;
                $reservedQuantities[$reservedKey] = $quantity;

                $items[] = [
                    'key' => $key,
                    'type' => 'quantity',
                    'material' => $material,
                    'quantity' => $quantity,
                ];

                $summary['total_items']++;
                $summary['total_units'] += $quantity;
                continue;
            }

            if ($entry['type'] === 'serial' && isset($entry['serial_id'])) {
                $serialId = (int) $entry['serial_id'];
                $serial = $serials->firstWhere('id', $serialId);

                if (! $serial || (int) $serial->material_id !== $materialId) {
                    unset($cart[$key]);
                    $dirty = true;
                    continue;
                }

                $serialsInCart[] = $serialId;

                $items[] = [
                    'key' => $key,
                    'type' => 'serial',
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

        if ($dirty) {
            $this->saveCart($request, $cart);
        }

        return [
            'items' => $items,
            'summary' => $summary,
            'reserved_quantities' => $reservedQuantities,
            'serials_in_cart' => $serialsInCart,
        ];
    }

    protected function normalizeCartItems(array $cart): array
    {
        $items = [];
        foreach ($cart as $entry) {
            if (! is_array($entry) || ! isset($entry['type'], $entry['material_id'])) {
                continue;
            }

            if ($entry['type'] === 'quantity') {
                $quantity = (int) ($entry['quantity'] ?? 0);

                if ($quantity < 1) {
                    continue;
                }

                $items[] = [
                    'type' => 'quantity',
                    'material_id' => (int) $entry['material_id'],
                    'quantity' => $quantity,
                ];
                continue;
            }

            if ($entry['type'] === 'serial' && isset($entry['serial_id'])) {
                $items[] = [
                    'type' => 'serial',
                    'material_id' => (int) $entry['material_id'],
                    'serial_id' => (int) $entry['serial_id'],
                ];
            }
        }

        return $items;
    }

    protected function pendingReturnReservations(StockLocation $technicianLocation): array
    {
        $pendingTransfers = Transfer::query()
            ->where('type', 'return')
            ->where('status', 'pending')
            ->where('from_location_id', $technicianLocation->id)
            ->with(['items'])
            ->get();

        $quantities = [];
        $serialIds = [];

        foreach ($pendingTransfers as $transfer) {
            foreach ($transfer->items as $item) {
                if ($item->material_serial_id) {
                    $serialIds[$item->material_serial_id] = true;
                    continue;
                }

                if ($item->material_id && $item->quantity) {
                    $key = 'quantity-' . $item->material_id;
                    $quantities[$key] = ($quantities[$key] ?? 0) + (int) $item->quantity;
                }
            }
        }

        return [
            'quantities' => $quantities,
            'serial_ids' => $serialIds,
        ];
    }

    protected function canManageReturns(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'logistics'], true);
    }

    protected function ensureCanManageReturns(User $user): void
    {
        if (! $this->canManageReturns($user)) {
            abort(403);
        }
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

    protected function warehouseLocation(): StockLocation
    {
        $location = StockLocation::warehouses()->first();

        if (! $location) {
            throw new RuntimeException('No se encontró la ubicación del almacén principal.');
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
