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
     * Scope to only available serial numbers.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope to only assigned serial numbers.
     */
    public function scopeAssigned(Builder $query): Builder
    {
        return $query->where('status', 'assigned');
    }

    /**
     * Material definition this serial belongs to.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Current location for the serial.
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
