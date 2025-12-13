<?php

namespace App\Services\Admin\AdminTransfer;

use App\Models\StockLocation;
use App\Models\User;
use RuntimeException;

class AdminTransferLocationService
{   // verifica si el usuario puede iniciar transferencias
    public function canInitiateTransfers(?User $user): bool
    {   // validar existencia del usuario
        if (! $user) {
            return false;
        }
        // verificar rol autorizado solo logistica y super admin
        return in_array($user->role, ['super_admin', 'logistics'], true);
    }
    // obtener ubicacion del almacen principal
    public function warehouseLocation(): StockLocation
    {   
        $location = StockLocation::warehouses()->first();
        // validar existencia del almacen
        if (! $location) {
            throw new RuntimeException('No se encontro la ubicacion del almacen principal.');
        }

        return $location;
    }
    // asegurar que el tecnico tenga una ubicacion de stock
    public function ensureTechnicianLocation(User $technician): StockLocation
    {
        $location = $technician->stockLocation()->first(); // obtener ubicacion existente

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
