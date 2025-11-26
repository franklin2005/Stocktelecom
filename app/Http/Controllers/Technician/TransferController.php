<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Material;
use App\Models\MaterialSerial;
use App\Models\Transfer;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\StockMovementLogger;
use App\Services\Technician\TechnicianTransfer\TechnicianTransferCartService;
use App\Services\Technician\TechnicianTransfer\TechnicianTransferDecisionService;
use App\Services\Technician\TechnicianTransfer\TechnicianTransferIndexService;
use App\Services\Technician\TechnicianTransfer\TechnicianTransferLocationService;
use App\Services\Technician\TechnicianTransfer\TechnicianTransferSendService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class TransferController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockMovementLogger $movementLogger,
        private readonly TechnicianTransferLocationService $locationService,
        private readonly TechnicianTransferCartService $cartService,
        private readonly TechnicianTransferIndexService $indexService,
        private readonly TechnicianTransferSendService $sendService,
        private readonly TechnicianTransferDecisionService $decisionService,
    ) {
    }

    /**
     * Muestra las transferencias pendientes y el historial reciente.
     */
    public function index(Request $request): View
    {
        $data = $this->indexService->buildIndexData($request);

        return view('technician.transfers', $data);
    }

    /**
     * Agrega materiales al carrito de transferencias.
     */
    public function addToCart(Request $request): RedirectResponse
    {
        $technician = $request->user();
        $location = $this->locationService->ensureTechnicianLocation($technician);
        $intent = $request->input('intent');

        if (! in_array($intent, ['quantity', 'serial'], true)) {
            return redirect()->route('technician.transfers')
                ->withErrors(['cart' => 'No se pudo determinar la accion solicitada.']);
        }

        $cart = $this->cartService->getCart($request);

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

            $this->cartService->saveCart($request, $cart);

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

        $this->cartService->saveCart($request, $cart);

        return redirect()->route('technician.transfers')
            ->with('status', 'Se anadieron ' . $serials->count() . ' numeros de serie a la transferencia.');
    }

    /**
     * Elimina un elemento del carrito.
     */
    public function removeFromCart(Request $request, string $itemKey): RedirectResponse
    {
        $cart = $this->cartService->getCart($request);

        if (isset($cart[$itemKey])) {
            unset($cart[$itemKey]);
            $this->cartService->saveCart($request, $cart);

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
        $this->cartService->clearCart($request);

        return redirect()->route('technician.transfers')
            ->with('status', 'Se vacio la lista de transferencia.');
    }

    /**
     * Envia los materiales seleccionados a otro tecnico.
     */
    public function send(Request $request): RedirectResponse
    {
        $technician = $request->user();
        $location = $this->locationService->ensureTechnicianLocation($technician);

        $cartData = $this->cartService->prepareCart($request, $location);
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

        $recipientLocation = $this->locationService->ensureTechnicianLocation($recipient);
        $notesInput = $request->input('notes');
        $notes = null;

        if (is_string($notesInput)) {
            $notesInput = trim($notesInput);

            if ($notesInput !== '') {
                $notes = mb_substr($notesInput, 0, 500);
            }
        }

        try {
            $this->sendService->createTransferFromCart(
                $technician,
                $location,
                $recipient,
                $recipientLocation,
                $cartItems,
                $cartSummary,
                $notes
            );
        } catch (RuntimeException $exception) {
            return redirect()->route('technician.transfers')
                ->withErrors(['cart' => $exception->getMessage()]);
        }

        $this->cartService->clearCart($request);

        return redirect()->route('technician.transfers')
            ->with('status', 'Transferencia generada: ' . $cartSummary['total_units'] . ' elementos enviados a ' . $recipient->name . '.');
    }

    /**
     * Acepta una transferencia y acredita los materiales al stock del tecnico.
     */
    public function accept(Request $request, Transfer $transfer): RedirectResponse
    {
        $technician = $request->user();
        $location = $this->locationService->ensureTechnicianLocation($technician);

        if ($transfer->to_location_id !== $location->id) {
            abort(403);
        }

        if ($transfer->status !== 'pending') {
            return back()->withErrors([
                'transfer' => 'La transferencia ya fue procesada.',
            ]);
        }

        try {
            $this->decisionService->acceptTransfer($transfer, $technician);
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
        $location = $this->locationService->ensureTechnicianLocation($technician);

        if ($transfer->to_location_id !== $location->id) {
            abort(403);
        }

        if ($transfer->status !== 'pending') {
            return back()->withErrors([
                'transfer' => 'La transferencia ya fue procesada.',
            ]);
        }

        try {
            $this->decisionService->rejectTransfer($transfer, $technician);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'transfer' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'Transferencia rechazada y stock restituido al origen.');
    }
}
