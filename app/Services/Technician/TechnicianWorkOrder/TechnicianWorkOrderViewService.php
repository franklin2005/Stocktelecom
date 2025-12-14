<?php

namespace App\Services\Technician\TechnicianWorkOrder;

use App\Models\Inventory;
use App\Models\MaterialSerial;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
// Servicio para construir datos de vista de ordenes de trabajo de tecnicos
class TechnicianWorkOrderViewService
{
    public function __construct(
        private readonly TechnicianWorkOrderLocationService $locationService,
    ) {
    }
    // construir datos para la vista de detalle de orden de trabajo
    public function buildShowData(WorkOrder $workOrder): array
    {   // cargar relaciones necesarias
        $workOrder->load(['items.material', 'items.serial.material']);
        // preparar etiqueta de estado
        $statusLabel = [
            'open' => ['label' => 'ABIERTA', 'class' => 'bg-warning'],
            'confirmed' => ['label' => 'CONFIRMADA', 'class' => 'bg-success'],
            'cancelled' => ['label' => 'CANCELADA', 'class' => 'bg-danger'],
        ][$workOrder->status] ?? ['label' => ucfirst($workOrder->status), 'class' => 'bg-secondary'];
            // separar items por tipo
        $quantityItems = $workOrder->items->whereNull('material_serial_id');
        $serialItems = $workOrder->items->whereNotNull('material_serial_id');
            // retornar datos para la vista
        return [
            'workOrder' => $workOrder,
            'statusLabel' => $statusLabel,
            'quantityItems' => $quantityItems,
            'serialItems' => $serialItems,
        ];
    }
    // construir datos para la vista de index de ordenes de trabajo
    public function buildIndexData(Request $request): array
    {   // obtener tecnico y su ubicacion
        $technician = $request->user();
        $location = $this->locationService->ensureTechnicianLocation($technician);
        // obtener orden de trabajo abierta mas reciente
        $openOrder = WorkOrder::query()
            ->with([
                'items.material',
                'items.serial.material',
            ])
            ->where('technician_id', $technician->id)
            ->where('status', 'open')
            ->orderByDesc('created_at')
            ->first();
                // obtener inventario en la ubicacion del tecnico
        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $location->id)
            ->orderBy('material_id')
            ->get()
            ->filter(fn ($item) => ($item->quantity ?? 0) > 0)
            ->values();
                // separar inventario en serializado y no serializado
        $nonSerializedInventory = $inventory
            ->filter(fn ($item) => $item->material && ! $item->material->is_serialized)
            ->values();

        $serializedInventory = $inventory
            ->filter(fn ($item) => $item->material && $item->material->is_serialized)
            ->values()
            ->keyBy('material_id');
                // obtener seriales disponibles excluyendo los que ay estan en la orden abierta
        $serialsInOrder = $openOrder
            ? $openOrder->items->pluck('material_serial_id')->filter()->all()
            : [];
                // obtener seriales disponibles en la ubicacion del tecnico
        $availableSerials = MaterialSerial::query()
            ->with('material')
            ->where('current_location_id', $location->id)
            ->where('status', 'assigned')
            ->when(! empty($serialsInOrder), fn ($query) => $query->whereNotIn('id', $serialsInOrder))
            ->orderBy('material_id')
            ->orderBy('serial_number')
            ->get()
            ->groupBy('material_id');
                // obtener ordenes de trabajo del tecnico con filtros de fecha
        $workOrders = WorkOrder::query()
            ->withCount('items')
            ->where('technician_id', $technician->id)
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', Carbon::parse($request->query('from'))->startOfDay()))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', Carbon::parse($request->query('to'))->endOfDay()))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();
                // retornar datos para la vista
        return [
            'location' => $location,
            'openOrder' => $openOrder,
            'nonSerializedInventory' => $nonSerializedInventory,
            'serializedInventory' => $serializedInventory,
            'availableSerials' => $availableSerials,
            'workOrders' => $workOrders,
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];
    }
}
