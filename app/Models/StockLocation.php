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
     * buscar por ubicaciones de usuarios.
     */
    public function scopeUsers(Builder $query): Builder
    {
        return $query->where('location_type', 'user');
    }

    /**
     * buscar por ubicaciones de almacenes.
     */
    public function scopeWarehouses(Builder $query): Builder
    {
        return $query->where('location_type', 'warehouse');
    }

    /**
     * relacion con inventarios.
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'location_id');
    }

    /**
     * numeros de serie ubicados en la StockLocation.
     */
    public function materialSerials(): HasMany
    {
        return $this->hasMany(MaterialSerial::class, 'current_location_id');
    }
}
