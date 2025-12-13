<?php

namespace App\Services\Admin\AdminReturns;

use App\Models\StockLocation;
use App\Models\User;
use RuntimeException;

class AdminReturnsLocationService
{   // obtener ubicacion del almacen principal
    public function warehouseLocation(): StockLocation
    {
        $location = StockLocation::warehouses()->first();
        // validar existencia del almacen
        if (! $location) {
            throw new RuntimeException('No se encontr?? la ubicaci??n del almac??n principal.');
        }

        return $location;
    }
    // asegurar que el tecnico tenga una ubicacion de stock
    public function ensureTechnicianLocation(User $technician): StockLocation
    {   // obtener ubicacion existente
        $location = $technician->stockLocation()->first();
        // si existe, retornarla
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
