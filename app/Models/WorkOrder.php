<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    protected $fillable = [
        'order_number',
        'technician_id',
        'technician_code',
        'technician_name',
        'status',
        'notes',
        'notes_author_type',
        'notes_author_name',
    ];

    protected $casts = [
        'technician_id' => 'integer',
    ];

    /**
     * buscar ordenes de trabajo confirmadas.
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * buscar ordenes de trabajo abiertas.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /**
     * comprobar si la orden de trabajo está abierta.
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * comprobar si la orden de trabajo está confirmada.
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    /**
     * comprobar si la orden de trabajo está cancelada.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * relación con los ítems de la orden de trabajo.
     */
    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    /**
     * relacion con tecnico que crea la orden de trabajo.
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id')->withTrashed();
    }
}
