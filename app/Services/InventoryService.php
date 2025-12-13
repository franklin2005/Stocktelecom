<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Material;
use App\Models\StockLocation;
use RuntimeException;

class InventoryService
{
    /**
     * Aumenta la cantidad de un material en una ubicacion especifica.
     */
    public function increase(StockLocation $location, Material $material, int $quantity): void
    {   // validar cantidad positiva
        if ($quantity < 1) {
            throw new RuntimeException('La cantidad debe ser positiva.');
        }
        // obtener inventario con bloqueo para actualizacion
        $inventory = Inventory::where('location_id', $location->id)
            ->where('material_id', $material->id)
            ->lockForUpdate()   
            ->first(); 
        // si no existe, crear nuevo registro
        if (! $inventory) {
            $inventory = new Inventory([
                'location_id' => $location->id,
                'material_id' => $material->id,
                'quantity' => 0,
            ]);
        }
        // aumentar cantidad y guardar
        $inventory->quantity = ($inventory->quantity ?? 0) + $quantity;
        $inventory->save();
    }

    /**
     * Reduce la cantidad de un material en una ubicacion especifica.
     */
    public function decrease(StockLocation $location, Material $material, int $quantity): void
    {   // validar cantidad positiva
        if ($quantity < 1) {
            throw new RuntimeException('La cantidad debe ser positiva.');
        }
        // obtener inventario con bloqueo para actualizacion
        $inventory = Inventory::where('location_id', $location->id)
            ->where('material_id', $material->id)
            ->lockForUpdate()
            ->first();
        // validar existencia y cantidad suficiente
        if (! $inventory || $inventory->quantity < $quantity) { 
            throw new RuntimeException('Stock insuficiente en la ubicacion solicitada.');
        }
        // reducir cantidad y guardar
        $inventory->quantity -= $quantity;
        $inventory->save();
    }
}
