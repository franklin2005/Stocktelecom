<?php

namespace App\Services\Admin\Material;

use App\Models\StockLocation;
use App\Models\User;
use RuntimeException;

class AdminMaterialLocationService
{
    public function warehouseLocation(): StockLocation
    {
        $location = StockLocation::warehouses()->first();

        if (! $location) {
            throw new RuntimeException('No se encontro la ubicacion de almacen.');
        }

        return $location;
    }

    public function ensureTechnicianLocation(User $technician): StockLocation
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
}
