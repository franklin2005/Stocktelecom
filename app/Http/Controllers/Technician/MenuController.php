<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\MaterialSerial;
use App\Models\StockLocation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuController extends Controller
{   // mostrar el stock del tecnico
    public function stock(Request $request): View
    {
        $technician = $request->user();
        $location = $technician->stockLocation()->first();
        // crear ubicacion de stock si no existe
        if (! $location) {
            $location = StockLocation::create([
                'location_type' => 'user',
                'ref_id' => $technician->id,
                'name' => 'Stock de ' . $technician->name,
            ]);
        }
        // obtener inventario y seriales asignados en la ubicacion
        $inventory = Inventory::query()
            ->with('material')
            ->where('location_id', $location->id)
            ->orderByDesc('quantity')
            ->get();
        // obtener seriales asignados
        $serials = MaterialSerial::query()
            ->with('material')
            ->where('current_location_id', $location->id)
            ->where('status', 'assigned')
            ->orderBy('material_id')
            ->orderBy('serial_number')
            ->get();
        // filtrar inventario no serializado con cantidad > 0
        $nonSerializedInventory = $inventory
            ->filter(fn ($item) => $item->material && ! $item->material->is_serialized && (int) $item->quantity > 0)
            ->values();
        $serializedGroups = $serials->groupBy('material_id');
        // retornar vista con datos
        return view('technician.stock', [
            'location' => $location,
            'nonSerializedInventory' => $nonSerializedInventory,
            'serializedGroups' => $serializedGroups,
        ]);
    }
    // mostrar transferencias del tecnico
    public function transfers(): View
    {
        return view('technician.transfers');
    }

}
