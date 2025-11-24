<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transfer extends Model
{
    protected $fillable = [
        'order_number',
        'type',
        'from_location_id',
        'to_location_id',
        'initiator_user_id',
        'requires_receiver_accept',
        'status',
        'accepted_at',
        'rejected_at',
        'notes',
    ];

    protected $casts = [
        'requires_receiver_accept' => 'boolean',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * buscar por tipo de transferencia.
     */
    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * buscar transferencias pendientes.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * buscar transferencias aceptadas.
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', 'accepted');
    }

    /**
     * buscar transferencias rechazadas.
     */
    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }

    /**
     * ubicacion de origen.
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'from_location_id');
    }

    /**
     * ubicacion de destino.
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'to_location_id');
    }

    /**
     * usuario que inició la transferencia.
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_user_id');
    }
}
