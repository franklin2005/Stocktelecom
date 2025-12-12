<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'type',
        'model',
        'is_serialized',
        'is_active',
    ];

    protected $casts = [
        'is_serialized' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * buscar por categorias y si está activo.
     */
public function scopeEquipo(Builder $query): Builder
{
    return $query->where('category', 'equipo');
}

public function scopeAcometidas(Builder $query): Builder
{
    return $query->where('category', 'acometida');
}

public function scopeRosetas(Builder $query): Builder
{
    return $query->where('category', 'roseta');
}

public function scopeOtros(Builder $query): Builder
{
    return $query->where('category', 'otro');
}


    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * seriales asociados a este material.
     */
    public function serials(): HasMany
    {
        return $this->hasMany(MaterialSerial::class);
    }

    /**
     * Inventarios asociados a este material.
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * ordenes de trabajo que incluyen este material.
     */
    public function workOrderItems(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    /**
     * ítems de transferencia que incluyen este material.
     */
    public function transferItems(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }
}
