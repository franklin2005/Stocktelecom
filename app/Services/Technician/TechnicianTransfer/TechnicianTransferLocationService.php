<?php

namespace App\Services\Technician\TechnicianTransfer;

use App\Models\StockLocation;
use App\Models\User;

class TechnicianTransferLocationService
{
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
