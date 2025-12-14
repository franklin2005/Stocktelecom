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

   // muestra la lista de transferencias del tecnico
    public function index(Request $request): View
    {   // obtener datos de transferencias
        $data = $this->indexService->buildIndexData($request);
        return view('technician.transfers', $data);
    }

   // agrega materiales al carrito de transferencia
    public function addToCart(Request $request): RedirectResponse
    {   // obtener tecnico y ubicacion
        $technician = $request->user();
        $location = $this->locationService->ensureTechnicianLocation($technician);
        $intent = $request->input('intent');
        if (! in_array($intent, ['quantity', 'serial'], true)) {
            return redirect()->route('technician.transfers')
                ->withErrors(['cart' => 'No se pudo determinar la acción solicitada.']);
        }
        // obtener carrito actual
        $cart = $this->cartService->getCart($request);
        // procesar segun tipo de agregado
        if ($intent === 'quantity') {
            $validated = $request->validate([
                'material_id' => ['required', 'integer'],
                'quantity' => ['required', 'integer', 'min:1'],
            ], [
                'material_id.required' => 'Selecciona un material valido.',
                'quantity.required' => 'Indica la cantidad a transferir.',
                'quantity.min' => 'La cantidad debe ser al menos 1.',
            ]);
            // obtener material no serializado
            $material = Material::query()
                ->whereKey($validated['material_id'])
                ->where('is_serialized', false)
                ->first();
            // validar existencia del material
            if (! $material) {
                return redirect()->route('technician.transfers')
                    ->withErrors(['material_id' => 'El material seleccionado no está disponible.']);
            }
            // verificar disponibilidad en inventario
            $inventory = Inventory::query()
                ->where('location_id', $location->id)
                ->where('material_id', $material->id)
                ->first();
            // calcular cantidades
            $available = $inventory?->quantity ?? 0;
            $reserved = $cart['quantity-' . $material->id]['quantity'] ?? 0;
            $requested = (int) $validated['quantity'];
            // validar cantidades
            if ($available <= 0) {
                return redirect()->route('technician.transfers')
                    ->withErrors(['material_id' => 'No cuentas con unidades disponibles de este material.']);
            }
            // validar que la cantidad solicitada no exceda la disponible
            if ($requested + $reserved > $available) {
                return redirect()->route('technician.transfers')
                    ->withErrors(['quantity' => 'Solo tienes ' . max($available - $reserved, 0) . ' unidades disponibles para transferir.']);
            }
            // agregar o actualizar item en el carrito
            $cart['quantity-' . $material->id] = [
                'type' => 'quantity',
                'material_id' => $material->id,
                'quantity' => $requested + $reserved,
            ];
            // guardar carrito actualizado
            $this->cartService->saveCart($request, $cart);
            return redirect()->route('technician.transfers')
                ->with('status', 'Se agregaron ' . $requested . ' unidades de ' . ucfirst($material->type) . ' a la transferencia.');
        }
        // procesar agregado de seriales
        $validated = $request->validate([
            'serial_ids' => ['required', 'array', 'min:1'],
            'serial_ids.*' => ['integer'],
        ], [
            'serial_ids.required' => 'Selecciona al menos un número de serie.',
        ]);
        // obtener seriales seleccionados
        $serialIds = collect($validated['serial_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        // obtener seriales del inventario del tecnico
        $serials = MaterialSerial::query()
            ->with('material')
            ->whereIn('id', $serialIds)
            ->where('current_location_id', $location->id)
            ->where('status', 'assigned')
            ->get();
        // validar que se recuperaron todos los seriales solicitados
        if ($serials->count() !== $serialIds->count()) {
            return redirect()->route('technician.transfers')
                ->withErrors(['serial_ids' => 'Alguno de los números de serie seleccionados ya no está disponible.']);
        }
        // verificar duplicados en el carrito
        $duplicates = $serials->pluck('id')->filter(fn ($id) => isset($cart['serial-' . $id]));
        // si hay duplicados, retornar con error    
        if ($duplicates->isNotEmpty()) {
            return redirect()->route('technician.transfers')
                ->withErrors(['serial_ids' => 'Los números de serie ' . $duplicates->implode(', ') . ' ya están en la lista.']);
        }
        // agregar seriales al carrito
        foreach ($serials as $serial) {
            $cart['serial-' . $serial->id] = [
                'type' => 'serial',
                'material_id' => $serial->material_id,
                'serial_id' => $serial->id,
            ];
        }
        // guardar carrito actualizado
        $this->cartService->saveCart($request, $cart);
        return redirect()->route('technician.transfers')
            ->with('status', 'Se anadieron ' . $serials->count() . ' números de serie a la transferencia.');
    }

  // elimina un item del carrito de transferencia
    public function removeFromCart(Request $request, string $itemKey): RedirectResponse
    {   // obtener carrito actual
        $cart = $this->cartService->getCart($request);
        // verificar existencia del item en el carrito
        if (isset($cart[$itemKey])) {
            unset($cart[$itemKey]);
            $this->cartService->saveCart($request, $cart);
            return redirect()->route('technician.transfers')
                ->with('status', 'Elemento retirado de la lista de transferencia.');
        }
        // si no existe, retornar con error
        return redirect()->route('technician.transfers')
            ->withErrors(['cart' => 'El elemento seleccionado ya no estaba en la lista.']);
    }

   // vacia el carrito de transferencia
    public function clearCart(Request $request): RedirectResponse
    {   // vaciar carrito
        $this->cartService->clearCart($request);
        return redirect()->route('technician.transfers')
            ->with('status', 'Se vació la lista de transferencia.');
    }

    // genera la transferencia a partir del carrito
    public function send(Request $request): RedirectResponse
    {   // obtener tecnico y ubicacion
        $technician = $request->user();
        $location = $this->locationService->ensureTechnicianLocation($technician);
        // preparar datos del carrito
        $cartData = $this->cartService->prepareCart($request, $location);
        $cartItems = $cartData['items'];
        $cartSummary = $cartData['summary'];
        // validar que el carrito no este vacio
        if (empty($cartItems)) {
            return redirect()->route('technician.transfers')
                ->withErrors(['cart' => 'Agrega materiales antes de generar una transferencia.']);
        }
        // validar destinatario
        $recipientId = (int) $request->input('technician_id');
        $recipient = User::technicians()->whereKey($recipientId)->first();
        // validar destinatario existente
        if (! $recipient) {
            return redirect()->route('technician.transfers')
                ->withErrors(['technician_id' => 'Selecciona un técnico destinatario valido.']);
        }
        // evitar autoasignacion
        if ($recipient->id === $technician->id) {
            return redirect()->route('technician.transfers')
                ->withErrors(['technician_id' => 'No puedes autoasignarte una transferencia.']);
        }
        // obtener ubicacion del destinatario
        $recipientLocation = $this->locationService->ensureTechnicianLocation($recipient);
        $notesInput = $request->input('notes');
        $notes = null;
        // procesar notas si se proporcionaron
        if (is_string($notesInput)) {
            $notesInput = trim($notesInput);
            // limitar longitud de notas
            if ($notesInput !== '') {
                $notes = mb_substr($notesInput, 0, 500);
            }
        }
        // intentar crear la transferencia
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
        // limpiar carrito
        $this->cartService->clearCart($request);
        return redirect()->route('technician.transfers')
            ->with('status', 'Transferencia generada: ' . $cartSummary['total_units'] . ' elementos enviados a ' . $recipient->name . '.');
    }

   // acepta una transferencia y actualiza el stock
    public function accept(Request $request, Transfer $transfer): RedirectResponse
    {   // obtener tecnico y ubicacion
        $technician = $request->user();
        $location = $this->locationService->ensureTechnicianLocation($technician);
        // validar que la transferencia pertenezca al tecnico
        if ($transfer->to_location_id !== $location->id) {
            abort(403);
        }
        // validar estado pendiente
        if ($transfer->status !== 'pending') {
            return back()->withErrors([
                'transfer' => 'La transferencia ya fue procesada.',
            ]);
        }
        // intentar aceptar la transferencia
        try {
            $this->decisionService->acceptTransfer($transfer, $technician);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'transfer' => $exception->getMessage(),
            ]);
        }
        return back()->with('status', 'Transferencia aceptada y stock actualizado.');
    }

   // rechaza una transferencia y restituye el stock al origen
    public function reject(Request $request, Transfer $transfer): RedirectResponse
    {
        $technician = $request->user();
        $location = $this->locationService->ensureTechnicianLocation($technician);
        // validar que la transferencia pertenezca al tecnico
        if ($transfer->to_location_id !== $location->id) {
            abort(403);
        }
        // validar estado pendiente
        if ($transfer->status !== 'pending') {
            return back()->withErrors([
                'transfer' => 'La transferencia ya fue procesada.',
            ]);
        }
        // intentar rechazar la transferencia
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
