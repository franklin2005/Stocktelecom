<?php

namespace App\Services\Technician\TechnicianWorkOrder;

use App\Models\Inventory;
use App\Models\MaterialSerial;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TechnicianWorkOrderViewService
{
    public function __construct(
        private readonly TechnicianWorkOrderLocationService $locationService,
    ) {
    }

    public function buildShowData(WorkOrder $workOrder): array
    {
        $workOrder->load(['items.material', 'items.serial.material']);

        $statusLabel = [
            'open' => ['label' => 'Abierta', 'class' => 'bg-warning text-dark'],
            'confirmed' => ['label' => 'Confirmada', 'class' => 'bg-success'],
            'cancelled' => ['label' => 'Cancelada', 'class' => 'bg-danger'],
        ][$workOrder->status] ?? ['label' => ucfirst($workOrder->status), 'class' => 'bg-secondary'];

        $quantityItems = $workOrder->items->whereNull('material_serial_id');
        $serialItems = $workOrder->items->whereNotNull('material_serial_id');

        return [
            'workOrder' => $workOrder,
            'statusLabel' => $statusLabel,
            'quantityItems' => $quantityItems,
            'serialItems' => $serialItems,
        ];
    }

    public function buildIndexData(Request $request): array
    {
        $technician = $request->user();
        $location = $this->locationService->ensureTechnicianLocation($technician);

        $openOrder = WorkOrder::query()
            ->with([
                'items.material',
                'items.serial.material',
            ])
            ->where('technician_id', $technician->id)
            ->where('status', 'open')
            ->orderByDesc('created_at')
            ->first();

        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $location->id)
            ->orderBy('material_id')
            ->get();

        $nonSerializedInventory = $inventory
            ->filter(fn ($item) => $item->material && ! $item->material->is_serialized)
            ->values();

        $serializedInventory = $inventory
            ->filter(fn ($item) => $item->material && $item->material->is_serialized)
            ->values()
            ->keyBy('material_id');

        $serialsInOrder = $openOrder
            ? $openOrder->items->pluck('material_serial_id')->filter()->all()
            : [];

        $availableSerials = MaterialSerial::query()
            ->with('material')
            ->where('current_location_id', $location->id)
            ->where('status', 'assigned')
            ->when(! empty($serialsInOrder), fn ($query) => $query->whereNotIn('id', $serialsInOrder))
            ->orderBy('material_id')
            ->orderBy('serial_number')
            ->get()
            ->groupBy('material_id');

        $workOrders = WorkOrder::query()
            ->withCount('items')
            ->where('technician_id', $technician->id)
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', Carbon::parse($request->query('from'))->startOfDay()))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', Carbon::parse($request->query('to'))->endOfDay()))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

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
