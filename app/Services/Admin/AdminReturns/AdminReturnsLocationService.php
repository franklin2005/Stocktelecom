<?php

namespace App\Services\Admin\AdminReturns;

use App\Models\StockLocation;
use App\Models\User;
use RuntimeException;

class AdminReturnsLocationService
{
    public function warehouseLocation(): StockLocation
    {
        $location = StockLocation::warehouses()->first();

        if (! $location) {
            throw new RuntimeException('No se encontr?? la ubicaci??n del almac??n principal.');
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
