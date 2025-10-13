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
     * Scope pending transfers.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope accepted transfers.
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', 'accepted');
    }

    /**
     * Transfer line items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }

    /**
     * Origin location.
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'from_location_id');
    }

    /**
     * Destination location.
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'to_location_id');
    }

    /**
     * User who initiated the transfer.
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_user_id');
    }
}

