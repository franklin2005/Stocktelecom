<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialSerial extends Model
{
    protected $fillable = [
        'material_id',
        'serial_number',
        'status',
        'current_location_id',
        'reserved_by_user_id',
        'reserved_at',
    ];

    protected $casts = [
        'material_id' => 'integer',
        'current_location_id' => 'integer',
        'reserved_by_user_id' => 'integer',
        'reserved_at' => 'datetime',
    ];

    /**
     * Buscar por seriales disponibles.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    /**
     * Buscar por seriales asignados.
     */
    public function scopeAssigned(Builder $query): Builder
    {
        return $query->where('status', 'assigned');
    }

    /**
     * Buscar seriales en transito.
     */
    public function scopeInTransit(Builder $query): Builder
    {
        return $query->where('status', 'in_transit');
    }

    /**
     * Relacion con material.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Ubicacion actual de la unidad.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'current_location_id');
    }

    /**
     * Usuario que tiene reservada la unidad.
     */
    public function reservedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserved_by_user_id');
    }
}
