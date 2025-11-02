<?php

namespace App\Http\Controllers\Technician;

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
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class TransferController extends Controller
{
    private const CART_SESSION_KEY = 'technician_transfer_cart';

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockMovementLogger $movementLogger,
    ) {
    }

    /**
     * Muestra las transferencias pendientes y el historial reciente.
     */
    public function index(Request $request): View
    {
        $technician = $request->user();
        $location = $this->ensureTechnicianLocation($technician);

        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $location->id)
            ->orderBy('material_id')
            ->get();

        $nonSerializedInventory = $inventory
            ->filter(fn ($item) => $item->material && ! $item->material->is_serialized && (int) $item->quantity > 0)
            ->values();
        $serializedInventory = $inventory
            ->filter(fn ($item) => $item->material && $item->material->is_serialized && (int) $item->quantity > 0)
            ->values();

        $availableSerials = MaterialSerial::query()
            ->with('material')
            ->where('current_location_id', $location->id)
            ->where('status', 'assigned')
            ->orderBy('material_id')
            ->orderBy('serial_number')
            ->get();

        $cartData = $this->prepareCart($request, $location);
        $cartItems = $cartData['items'];
        $cartSummary = $cartData['summary'];
        $reservedQuantities = $cartData['reserved_quantities'];

        $recipientSearch = trim((string) $request->query('recipient_search', ''));
        $recipientQuery = User::technicians()
            ->where('id', '!=', $technician->id);

        if ($recipientSearch !== '') {
            $recipientQuery->where('name', 'like', '%' . $recipientSearch . '%');
        }

        $recipientTechnicians = $recipientQuery
            ->orderBy('name')
            ->limit(25)
            ->get();

        $pendingTransfers = Transfer::query()
            ->with([
                'items.material',
                'items.serial',
                'fromLocation',
            ])
            ->where('type', 'transfer')
            ->where('to_location_id', $location->id)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();

        $pendingReturns = Transfer::query()
            ->with([
                'items.material',
                'items.serial',
                'fromLocation',
                'toLocation',
            ])
            ->where('type', 'return')
            ->where('from_location_id', $location->id)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();

        $recentTransferHistory = Transfer::query()
            ->with([
                'items.material',
                'items.serial',
                'fromLocation',
                'toLocation',
            ])
            ->where('type', 'transfer')
            ->where('to_location_id', $location->id)
            ->whereIn('status', ['accepted', 'rejected'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $recentReturnHistory = Transfer::query()
            ->with([
                'items.material',
                'items.serial',
                'fromLocation',
                'toLocation',
            ])
            ->where('type', 'return')
            ->where('from_location_id', $location->id)
            ->whereIn('status', ['accepted', 'rejected'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return view('technician.transfers', [
            'pendingTransfers' => $pendingTransfers,
            'pendingReturns' => $pendingReturns,
            'recentTransferHistory' => $recentTransferHistory,
            'recentReturnHistory' => $recentReturnHistory,
            'location' => $location,
            'nonSerializedInventory' => $nonSerializedInventory,
            'serializedInventory' => $serializedInventory,
            'availableSerials' => $availableSerials,
            'cartItems' => $cartItems,
            'cartSummary' => $cartSummary,
            'reservedQuantities' => $reservedQuantities,
            'serialsInCart' => $cartData['serials_in_cart'],
            'recipientTechnicians' => $recipientTechnicians,
            'recipientSearch' => $recipientSearch,
        ]);
    }

    /**
     * Agrega materiales al carrito de transferencias.
     */
    public function addToCart(Request $request): RedirectResponse
    {
        $technician = $request->user();
        $location = $this->ensureTechnicianLocation($technician);
        $intent = $request->input('intent');

        if (! in_array($intent, ['quantity', 'serial'], true)) {
            return redirect()->route('technician.transfers')
                ->withErrors(['cart' => 'No se pudo determinar la accion solicitada.']);
        }

        $cart = $this->getCart($request);

        if ($intent === 'quantity') {
            $validated = $request->validate([
                'material_id' => ['required', 'integer'],
                'quantity' => ['required', 'integer', 'min:1'],
            ], [
                'material_id.required' => 'Selecciona un material valido.',
                'quantity.required' => 'Indica la cantidad a transferir.',
                'quantity.min' => 'La cantidad debe ser al menos 1.',
            ]);

            $material = Material::query()
                ->whereKey($validated['material_id'])
                ->where('is_serialized', false)
                ->first();

            if (! $material) {
                return redirect()->route('technician.transfers')
                    ->withErrors(['material_id' => 'El material seleccionado no esta disponible.']);
            }

            $inventory = Inventory::query()
                ->where('location_id', $location->id)
                ->where('material_id', $material->id)
                ->first();

            $available = $inventory?->quantity ?? 0;
            $reserved = $cart['quantity-' . $material->id]['quantity'] ?? 0;
            $requested = (int) $validated['quantity'];

            if ($available <= 0) {
                return redirect()->route('technician.transfers')
                    ->withErrors(['material_id' => 'No cuentas con unidades disponibles de este material.']);
            }

            if ($requested + $reserved > $available) {
                return redirect()->route('technician.transfers')
                    ->withErrors(['quantity' => 'Solo tienes ' . max($available - $reserved, 0) . ' unidades disponibles para transferir.']);
            }

            $cart['quantity-' . $material->id] = [
                'type' => 'quantity',
                'material_id' => $material->id,
                'quantity' => $requested + $reserved,
            ];

            $this->saveCart($request, $cart);

            return redirect()->route('technician.transfers')
                ->with('status', 'Se agregaron ' . $requested . ' unidades de ' . ucfirst($material->type) . ' a la transferencia.');
        }

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

        $serials = MaterialSerial::query()
            ->with('material')
            ->whereIn('id', $serialIds)
            ->where('current_location_id', $location->id)
            ->where('status', 'assigned')
            ->get();

        if ($serials->count() !== $serialIds->count()) {
            return redirect()->route('technician.transfers')
                ->withErrors(['serial_ids' => 'Alguno de los numeros de serie seleccionados ya no esta disponible.']);
        }

        $duplicates = $serials->pluck('id')->filter(fn ($id) => isset($cart['serial-' . $id]));

        if ($duplicates->isNotEmpty()) {
            return redirect()->route('technician.transfers')
                ->withErrors(['serial_ids' => 'Los numeros de serie ' . $duplicates->implode(', ') . ' ya estan en la lista.']);
        }

        foreach ($serials as $serial) {
            $cart['serial-' . $serial->id] = [
                'type' => 'serial',
                'material_id' => $serial->material_id,
                'serial_id' => $serial->id,
            ];
        }

        $this->saveCart($request, $cart);

        return redirect()->route('technician.transfers')
            ->with('status', 'Se anadieron ' . $serials->count() . ' numeros de serie a la transferencia.');
    }

    /**
     * Elimina un elemento del carrito.
     */
    public function removeFromCart(Request $request, string $itemKey): RedirectResponse
    {
        $cart = $this->getCart($request);

        if (isset($cart[$itemKey])) {
            unset($cart[$itemKey]);
            $this->saveCart($request, $cart);

            return redirect()->route('technician.transfers')
                ->with('status', 'Elemento retirado de la lista de transferencia.');
        }

        return redirect()->route('technician.transfers')
            ->withErrors(['cart' => 'El elemento seleccionado ya no estaba en la lista.']);
    }

    /**
     * Limpia por completo el carrito.
     */
    public function clearCart(Request $request): RedirectResponse
    {
        $this->clearCartSession($request);

        return redirect()->route('technician.transfers')
            ->with('status', 'Se vacio la lista de transferencia.');
    }

    /**
     * Envia los materiales seleccionados a otro tecnico.
     */
    public function send(Request $request): RedirectResponse
    {
        $technician = $request->user();
        $location = $this->ensureTechnicianLocation($technician);

        $cartData = $this->prepareCart($request, $location);
        $cartItems = $cartData['items'];
        $cartSummary = $cartData['summary'];

        if (empty($cartItems)) {
            return redirect()->route('technician.transfers')
                ->withErrors(['cart' => 'Agrega materiales antes de generar una transferencia.']);
        }

        $recipientId = (int) $request->input('technician_id');
        $recipient = User::technicians()->whereKey($recipientId)->first();

        if (! $recipient) {
            return redirect()->route('technician.transfers')
                ->withErrors(['technician_id' => 'Selecciona un tecnico destinatario valido.']);
        }

        if ($recipient->id === $technician->id) {
            return redirect()->route('technician.transfers')
                ->withErrors(['technician_id' => 'No puedes autoasignarte una transferencia.']);
        }

        $recipientLocation = $this->ensureTechnicianLocation($recipient);
        $notesInput = $request->input('notes');
        $notes = null;

        if (is_string($notesInput)) {
            $notesInput = trim($notesInput);

            if ($notesInput !== '') {
                $notes = mb_substr($notesInput, 0, 500);
            }
        }

        $userId = $request->user()->id;

        $materialIds = collect($cartItems)
            ->map(fn ($item) => $item['material']->id ?? null)
            ->filter()
            ->unique()
            ->values();
        $materials = Material::query()
            ->whereIn('id', $materialIds)
            ->get()
            ->keyBy('id');

        $serialIds = collect($cartItems)
            ->where('type', 'serial')
            ->map(fn ($item) => $item['serial']->id ?? null)
            ->filter()
            ->unique()
            ->values();

        try {
            DB::transaction(function () use ($location, $recipientLocation, $userId, $materials, $serialIds, $cartItems, $notes) {
                $transfer = Transfer::create([
                    'order_number' => $this->generateTransferNumber(),
                    'type' => 'transfer',
                    'from_location_id' => $location->id,
                    'to_location_id' => $recipientLocation->id,
                    'initiator_user_id' => $userId,
                    'requires_receiver_accept' => true,
                    'status' => 'pending',
                    'notes' => $notes,
                ]);

                $serialModels = $serialIds->isEmpty() ? collect() : MaterialSerial::query()
                    ->whereIn('id', $serialIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($cartItems as $item) {
                    $materialId = $item['material']->id ?? null;
                    $material = $materialId !== null ? $materials->get($materialId) : null;

                    if (! $material) {
                        throw new RuntimeException('No se pudo recuperar la informacion del material seleccionado.');
                    }

                    if ($item['type'] === 'quantity') {
                        $this->inventoryService->decrease($location, $material, (int) $item['quantity']);

                        TransferItem::create([
                            'transfer_id' => $transfer->id,
                            'material_id' => $material->id,
                            'quantity' => (int) $item['quantity'],
                        ]);

                        $this->movementLogger->log(
                            'transfer_out',
                            $material,
                            null,
                            $location,
                            $recipientLocation,
                            (int) $item['quantity'],
                            'transfer',
                            $transfer->id,
                            $userId
                        );

                        continue;
                    }

                    $serialId = $item['serial']->id ?? null;
                    $serial = $serialId !== null ? $serialModels->get($serialId) : null;

                    if (! $serial || $serial->current_location_id !== $location->id || $serial->status !== 'assigned') {
                        throw new RuntimeException('Alguno de los numeros de serie seleccionados ya no esta disponible.');
                    }

                    $serial->update([
                        'status' => 'assigned',
                        'current_location_id' => null,
                    ]);

                    $this->inventoryService->decrease($location, $material, 1);

                    TransferItem::create([
                        'transfer_id' => $transfer->id,
                        'material_id' => $material->id,
                        'material_serial_id' => $serial->id,
                    ]);

                    $this->movementLogger->log(
                        'transfer_out',
                        $material,
                        $serial,
                        $location,
                        $recipientLocation,
                        1,
                        'transfer',
                        $transfer->id,
                        $userId
                    );
                }
            });
        } catch (RuntimeException $exception) {
            return redirect()->route('technician.transfers')
                ->withErrors(['cart' => $exception->getMessage()]);
        }

        $this->clearCartSession($request);

        return redirect()->route('technician.transfers')
            ->with('status', 'Transferencia generada: ' . $cartSummary['total_units'] . ' elementos enviados a ' . $recipient->name . '.');
    }
    /**
     * Acepta una transferencia y acredita los materiales al stock del tecnico.
     */
    public function accept(Request $request, Transfer $transfer): RedirectResponse
    {
        $technician = $request->user();
        $location = $this->ensureTechnicianLocation($technician);

        if ($transfer->to_location_id !== $location->id) {
            abort(403);
        }

        if ($transfer->status !== 'pending') {
            return back()->withErrors([
                'transfer' => 'La transferencia ya fue procesada.',
            ]);
        }

        $userId = $request->user()->id;

        try {
            DB::transaction(function () use ($transfer, $location, $userId) {
                $transfer->loadMissing(['items.material', 'items.serial', 'fromLocation']);

                foreach ($transfer->items as $item) {
                    if ($item->material_serial_id) {
                        $serial = MaterialSerial::query()
                            ->whereKey($item->material_serial_id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $serial->update([
                            'status' => 'assigned',
                            'current_location_id' => $location->id,
                        ]);

                        $this->inventoryService->increase($location, $item->material, 1);

                        $this->movementLogger->log(
                            'transfer_in',
                            $item->material,
                            $serial,
                            $transfer->fromLocation,
                            $location,
                            1,
                            'transfer',
                            $transfer->id,
                            $userId
                        );
                    } elseif ($item->quantity) {
                        $this->inventoryService->increase($location, $item->material, (int) $item->quantity);

                        $this->movementLogger->log(
                            'transfer_in',
                            $item->material,
                            null,
                            $transfer->fromLocation,
                            $location,
                            (int) $item->quantity,
                            'transfer',
                            $transfer->id,
                            $userId
                        );
                    }
                }

                $transfer->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                ]);
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'transfer' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'Transferencia aceptada y stock actualizado.');
    }

    /**
     * Rechaza una transferencia y restituye el stock al almacen de origen.
     */
    public function reject(Request $request, Transfer $transfer): RedirectResponse
    {
        $technician = $request->user();
        $location = $this->ensureTechnicianLocation($technician);

        if ($transfer->to_location_id !== $location->id) {
            abort(403);
        }

        if ($transfer->status !== 'pending') {
            return back()->withErrors([
                'transfer' => 'La transferencia ya fue procesada.',
            ]);
        }

        $userId = $request->user()->id;

        try {
            DB::transaction(function () use ($transfer, $userId) {
                $transfer->loadMissing(['items.material', 'items.serial', 'fromLocation']);

                $fromLocation = $transfer->fromLocation;

                if (! $fromLocation) {
                    throw new RuntimeException('No fue posible determinar la ubicacion de origen.');
                }

                foreach ($transfer->items as $item) {
                    if ($item->material_serial_id) {
                        $serial = MaterialSerial::query()
                            ->whereKey($item->material_serial_id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $serial->update([
                            'status' => 'available',
                            'current_location_id' => $fromLocation->id,
                        ]);

                        $this->inventoryService->increase($fromLocation, $item->material, 1);

                        $this->movementLogger->log(
                            'transfer_in',
                            $item->material,
                            $serial,
                            null,
                            $fromLocation,
                            1,
                            'transfer',
                            $transfer->id,
                            $userId
                        );
                    } elseif ($item->quantity) {
                        $this->inventoryService->increase($fromLocation, $item->material, (int) $item->quantity);

                        $this->movementLogger->log(
                            'transfer_in',
                            $item->material,
                            null,
                            null,
                            $fromLocation,
                            (int) $item->quantity,
                            'transfer',
                            $transfer->id,
                            $userId
                        );
                    }
                }

                $transfer->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                ]);
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'transfer' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'Transferencia rechazada y stock restituido al origen.');
    }

    /**
     * Garantiza la existencia de la ubicacion de stock del tecnico.
     */
    protected function ensureTechnicianLocation($technician): StockLocation
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
     * Prepara la informacion del carrito para la vista.
     *
     * @return array{items: array<int, array>, summary: array<string, int>, reserved_quantities: array<string, int>, serials_in_cart: array<int>}
     */
    protected function prepareCart(Request $request, StockLocation $location): array
    {
        $cart = $this->getCart($request);

        if (empty($cart)) {
            return [
                'items' => [],
                'summary' => [
                    'total_items' => 0,
                    'total_units' => 0,
                ],
                'reserved_quantities' => [],
                'serials_in_cart' => [],
            ];
        }

        $materialIds = [];
        $serialIds = [];

        foreach ($cart as $entry) {
            if (! is_array($entry) || ! isset($entry['type'], $entry['material_id'])) {
                continue;
            }

            $materialIds[] = (int) $entry['material_id'];

            if (($entry['type'] ?? '') === 'serial' && isset($entry['serial_id'])) {
                $serialIds[] = (int) $entry['serial_id'];
            }
        }

        $materialIds = array_unique($materialIds);

        $materials = Material::query()
            ->whereIn('id', $materialIds)
            ->get()
            ->keyBy('id');

        $inventories = Inventory::query()
            ->where('location_id', $location->id)
            ->whereIn('material_id', $materialIds)
            ->get()
            ->keyBy('material_id');

        $serialModels = empty($serialIds)
            ? collect()
            : MaterialSerial::query()
                ->with('material')
                ->whereIn('id', $serialIds)
                ->get()
                ->keyBy('id');

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

            $materialId = (int) $entry['material_id'];
            $material = $materials->get($materialId);

            if (! $material) {
                unset($cart[$key]);
                $dirty = true;
                continue;
            }

            if ($entry['type'] === 'quantity') {
                $quantity = (int) ($entry['quantity'] ?? 0);
                $available = $inventories->get($materialId)?->quantity ?? 0;

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
                    $dirty = true;
                    continue;
                }

                $reservedQuantities['quantity-' . $materialId] = $quantity;

                $items[] = [
                    'key' => $key,
                    'type' => 'quantity',
                    'material_id' => $material->id,
                    'material' => $material,
                    'quantity' => $quantity,
                ];

                $summary['total_items']++;
                $summary['total_units'] += $quantity;
                continue;
            }

            if ($entry['type'] === 'serial' && isset($entry['serial_id'])) {
                $serialId = (int) $entry['serial_id'];
                $serial = $serialModels->get($serialId);

                if (! $serial || $serial->current_location_id !== $location->id || $serial->status !== 'assigned') {
                    unset($cart[$key]);
                    $dirty = true;
                    continue;
                }

                $serialsInCart[] = $serialId;

                $items[] = [
                    'key' => $key,
                    'type' => 'serial',
                    'material_id' => $material->id,
                    'material' => $material,
                    'serial_id' => $serial->id,
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
            'items' => array_values($items),
            'summary' => $summary,
            'reserved_quantities' => $reservedQuantities,
            'serials_in_cart' => $serialsInCart,
        ];
    }

    /**
     * Obtiene el carrito de la sesion.
     *
     * @return array<string, array>
     */
    protected function getCart(Request $request): array
    {
        return $request->session()->get(self::CART_SESSION_KEY, []);
    }

    /**
     * Persiste el carrito en la sesion.
     *
     * @param  array<string, array>  $cart
     */
    protected function saveCart(Request $request, array $cart): void
    {
        $request->session()->put(self::CART_SESSION_KEY, $cart);
    }

    /**
     * Elimina el carrito de la sesion.
     */
    protected function clearCartSession(Request $request): void
    {
        $request->session()->forget(self::CART_SESSION_KEY);
    }

    /**
     * Genera un identificador unico de transferencia.
     */
    protected function generateTransferNumber(): string
    {
        do {
            $number = 'TRF-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Transfer::where('order_number', $number)->exists());

        return $number;
    }
}
