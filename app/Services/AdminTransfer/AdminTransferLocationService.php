<?php

namespace App\Services\AdminTransfer;

use App\Models\StockLocation;
use App\Models\User;
use RuntimeException;

class AdminTransferLocationService
{
    public function canInitiateTransfers(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return in_array($user->role, ['super_admin', 'logistics'], true);
    }

    public function warehouseLocation(): StockLocation
    {
        $location = StockLocation::warehouses()->first();

        if (! $location) {
            throw new RuntimeException('No se encontro la ubicacion del almacen principal.');
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
