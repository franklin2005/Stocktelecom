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
    {
        if ($quantity < 1) {
            throw new RuntimeException('La cantidad debe ser positiva.');
        }

        $inventory = Inventory::where('location_id', $location->id)
            ->where('material_id', $material->id)
            ->lockForUpdate()
            ->first();

        if (! $inventory) {
            $inventory = new Inventory([
                'location_id' => $location->id,
                'material_id' => $material->id,
                'quantity' => 0,
            ]);
        }

        $inventory->quantity = ($inventory->quantity ?? 0) + $quantity;
        $inventory->save();
    }

    /**
     * Reduce la cantidad de un material en una ubicacion especifica.
     */
    public function decrease(StockLocation $location, Material $material, int $quantity): void
    {
        if ($quantity < 1) {
            throw new RuntimeException('La cantidad debe ser positiva.');
        }

        $inventory = Inventory::where('location_id', $location->id)
            ->where('material_id', $material->id)
            ->lockForUpdate()
            ->first();

        if (! $inventory || $inventory->quantity < $quantity) {
            throw new RuntimeException('Stock insuficiente en la ubicacion solicitada.');
        }

        $inventory->quantity -= $quantity;
        $inventory->save();
    }
}
