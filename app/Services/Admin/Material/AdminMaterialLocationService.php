<?php

namespace App\Services\Admin\Material;

use App\Models\StockLocation;
use App\Models\User;
use RuntimeException;

class AdminMaterialLocationService
{   
    public function warehouseLocation(): StockLocation 
    {// obtener ubicacion del almacen principal
        $location = StockLocation::warehouses()->first();
        // validar existencia del almacen
        if (! $location) {
            throw new RuntimeException('No se encontro la ubicacion de almacen.');
        }

        return $location;
    }
    // asegurar que el tecnico tenga una ubicacion de stock
    public function ensureTechnicianLocation(User $technician): StockLocation
    {   // obtener ubicacion existente
        $location = $technician->stockLocation()->first();

        if ($location) {
            return $location;
        }
        // crear nueva ubicacion de stock para el tecnico si no extiste ubicacion
        return StockLocation::create([
            'location_type' => 'user',
            'ref_id' => $technician->id,
            'name' => 'Stock de ' . $technician->name,
        ]);
    }
}
