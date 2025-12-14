<?php

namespace App\Services\Technician\TechnicianTransfer;

use App\Models\StockLocation;
use App\Models\User;
// Servicio para manejar ubicaciones de stock de tecnicos
class TechnicianTransferLocationService
{   // asegurar que el tecnico tenga una ubicacion de stock
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
