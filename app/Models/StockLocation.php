<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockLocation extends Model
{
    protected $fillable = [
        'location_type',
        'ref_id',
        'name',
    ];

    protected $casts = [
        'ref_id' => 'integer',
    ];

    /**
     * Scope query to only user locations.
     */
    public function scopeUsers(Builder $query): Builder
    {
        return $query->where('location_type', 'user');
    }

    /**
     * Scope query to only warehouse locations.
     */
    public function scopeWarehouses(Builder $query): Builder
    {
        return $query->where('location_type', 'warehouse');
    }

    /**
     * Inventory records associated with the location.
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'location_id');
    }

    /**
     * Serialized materials currently at the location.
     */
    public function materialSerials(): HasMany
    {
        return $this->hasMany(MaterialSerial::class, 'current_location_id');
    }
}
