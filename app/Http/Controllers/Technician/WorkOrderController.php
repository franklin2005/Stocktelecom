<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\InventoryService;
use App\Services\StockMovementLogger;
use App\Services\Technician\TechnicianWorkOrder\TechnicianWorkOrderAuthorizationService;
use App\Services\Technician\TechnicianWorkOrder\TechnicianWorkOrderCreationService;
use App\Services\Technician\TechnicianWorkOrder\TechnicianWorkOrderItemService;
use App\Services\Technician\TechnicianWorkOrder\TechnicianWorkOrderLocationService;
use App\Services\Technician\TechnicianWorkOrder\TechnicianWorkOrderStatusService;
use App\Services\Technician\TechnicianWorkOrder\TechnicianWorkOrderViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class WorkOrderController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly StockMovementLogger $movementLogger,
        private readonly TechnicianWorkOrderLocationService $locationService,
        private readonly TechnicianWorkOrderAuthorizationService $authorizationService,
        private readonly TechnicianWorkOrderViewService $viewService,
        private readonly TechnicianWorkOrderCreationService $creationService,
        private readonly TechnicianWorkOrderItemService $itemService,
        private readonly TechnicianWorkOrderStatusService $statusService,
    ) {
    }

    /**
     * Muestra el detalle de una orden para consulta.
     */
    public function show(Request $request, WorkOrder $workOrder): View
    {
        $technician = $request->user();

        if ($workOrder->technician_id !== $technician->id) {
            abort(403);
        }

        $data = $this->viewService->buildShowData($workOrder);

        return view('technician.work-orders.show', $data);
    }

    /**
     * Listado y gestion de ordenes de trabajo del tecnico.
     */
    public function index(Request $request): View
    {
        $data = $this->viewService->buildIndexData($request);

        return view('technician.work-orders', $data);
    }

    /**
     * Crea una nueva orden de trabajo en estado abierto.
     */
    public function store(Request $request): RedirectResponse
    {
        $technician = $request->user();

        $validated = $request->validate([
            'order_number' => ['required', 'string', 'max:50', 'unique:work_orders,order_number'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'order_number.required' => 'Ingresa el numero de orden.',
            'order_number.unique' => 'El numero de orden ya existe.',
        ]);

        try {
            $this->creationService->createOpenOrder($technician, $validated['order_number'], $validated['notes'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'order_number' => $exception->getMessage(),
            ])->withInput();
        }

        return redirect()->route('technician.work-orders')
            ->with('status', 'Orden de trabajo creada en estado abierta.');
    }

    /**
     * Agrega materiales no serializados a la orden abierta.
     */
    public function addQuantityItem(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $technician = $request->user();
        $this->authorizationService->assertOwnsOpenOrder($workOrder, $technician);

        $validated = $request->validate([
            'material_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
        ], [
            'material_id.required' => 'Selecciona un material valido.',
            'quantity.required' => 'Indica la cantidad a utilizar.',
            'quantity.min' => 'La cantidad debe ser al menos 1.',
        ]);

        try {
            $this->itemService->addQuantityItem($workOrder, $technician, (int) $validated['material_id'], (int) $validated['quantity']);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'quantity' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'Se registraron ' . (int) $validated['quantity'] . ' unidades en la orden.');
    }

    /**
     * Agrega materiales serializados a la orden abierta.
     */
    public function addSerialItem(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $technician = $request->user();
        $this->authorizationService->assertOwnsOpenOrder($workOrder, $technician);

        $validated = $request->validate([
            'serial_ids' => ['required', 'array', 'min:1'],
            'serial_ids.*' => ['integer'],
        ], [
            'serial_ids.required' => 'Selecciona al menos un numero de serie.',
        ]);

        try {
            $this->itemService->addSerialItems($workOrder, $technician, $validated['serial_ids']);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'serial_ids' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'Se agregaron ' . count($validated['serial_ids']) . ' numeros de serie a la orden.');
    }

    /**
     * Elimina un item de la orden abierta.
     */
    public function removeItem(Request $request, WorkOrder $workOrder, WorkOrderItem $item): RedirectResponse
    {
        $technician = $request->user();
        $this->authorizationService->assertOwnsOpenOrder($workOrder, $technician);

        try {
            $this->itemService->removeItem($workOrder, $item, $technician);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'work_order' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'El material se elimino de la orden y se regreso al stock.');
    }

   /**
 * Confirma la orden y descuenta el stock utilizado (si hay materiales).
 */
public function confirm(Request $request, WorkOrder $workOrder): RedirectResponse
{
    $technician = $request->user();
    $this->authorizationService->assertOwnsOpenOrder($workOrder, $technician);

    $location = $this->locationService->ensureTechnicianLocation($technician);

    try {
        $this->statusService->confirmOrder($workOrder, $technician, $location);
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
        $this->authorizationService->assertOwnsOpenOrder($workOrder, $technician);

        $location = $this->locationService->ensureTechnicianLocation($technician);

        $this->statusService->cancelOrder($workOrder, $technician, $location);

        return redirect()->route('technician.work-orders')
            ->with('status', 'Orden cancelada y materiales restituidos al stock.');
    }
}
