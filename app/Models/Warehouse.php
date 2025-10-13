<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Warehouse extends Model
{
    protected $fillable = [
        'code',
        'name',
    ];

    /**
     * Get the stock location representing this warehouse.
     */
    public function stockLocation(): HasOne
    {
        return $this->hasOne(StockLocation::class, 'ref_id')
            ->where('location_type', 'warehouse');
    }
}
