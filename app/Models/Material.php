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
     * Scope materials by category helpers.
     */
    public function scopeEquipment(Builder $query): Builder
    {
        return $query->where('category', 'equipment');
    }

    public function scopeAcometidas(Builder $query): Builder
    {
        return $query->where('category', 'acometida');
    }

    public function scopeRosetas(Builder $query): Builder
    {
        return $query->where('category', 'roseta');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Serialized units for the material.
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
     * Work order items referencing the material.
     */
    public function workOrderItems(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    /**
     * Transfer items referencing the material.
     */
    public function transferItems(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }
}
