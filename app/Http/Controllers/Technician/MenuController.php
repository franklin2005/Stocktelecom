<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\MaterialSerial;
use App\Models\StockLocation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function stock(Request $request): View
    {
        $technician = $request->user();
        $location = $technician->stockLocation()->first();

        if (! $location) {
            $location = StockLocation::create([
                'location_type' => 'user',
                'ref_id' => $technician->id,
                'name' => 'Stock de ' . $technician->name,
            ]);
        }

        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $location->id)
            ->orderByDesc('quantity')
            ->get();

        $serials = MaterialSerial::query()
            ->with('material')
            ->where('current_location_id', $location->id)
            ->where('status', 'assigned')
            ->orderBy('material_id')
            ->orderBy('serial_number')
            ->get();

        $nonSerializedInventory = $inventory
            ->filter(fn ($item) => $item->material && ! $item->material->is_serialized)
            ->values();
        $serializedAggregates = $inventory
            ->filter(fn ($item) => $item->material && $item->material->is_serialized)
            ->values();
        $serializedGroups = $serials->groupBy('material_id');

        return view('technician.stock', [
            'location' => $location,
            'nonSerializedInventory' => $nonSerializedInventory,
            'serializedGroups' => $serializedGroups,
            'serializedAggregates' => $serializedAggregates,
        ]);
    }

    public function transfers(): View
    {
        return view('technician.transfers');
    }

}
