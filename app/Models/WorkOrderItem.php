<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderItem extends Model
{
    protected $fillable = [
        'work_order_id',
        'material_id',
        'quantity',
        'material_serial_id',
    ];

    protected $casts = [
        'work_order_id' => 'integer',
        'material_id' => 'integer',
        'material_serial_id' => 'integer',
        'quantity' => 'integer',
    ];

    /**
     * relacion con la orden de trabajo.
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * relacion con el material.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * relacion con el material con numero de serie (si aplica).
     */
    public function serial(): BelongsTo
    {
        return $this->belongsTo(MaterialSerial::class, 'material_serial_id');
    }
}
