<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Transfer;

class StockMovement extends Model
{
    protected $fillable = [
        'movement_type',
        'material_id',
        'material_serial_id',
        'from_location_id',
        'to_location_id',
        'quantity',
        'reference_type',
        'reference_id',
        'performed_at',
        'performed_by',
    ];

    protected $casts = [
        'material_id' => 'integer',
        'material_serial_id' => 'integer',
        'from_location_id' => 'integer',
        'to_location_id' => 'integer',
        'quantity' => 'integer',
        'reference_id' => 'integer',
        'performed_at' => 'datetime',
        'performed_by' => 'integer',
    ];

    /**
     * Scope movements by movement type.
     */
    public function scopeMovementType(Builder $query, string $type): Builder
    {
        return $query->where('movement_type', $type);
    }

    /**
     * Scope movements by performed_at date range.
     */
    public function scopeBetweenDates(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('performed_at', [$from, $to]);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(MaterialSerial::class, 'material_serial_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'to_location_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Related transfer when reference_type is transfer.
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class, 'reference_id')->where('reference_type', 'transfer');
    }
}

