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
     * Parent work order.
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Material reference.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Serialized material reference.
     */
    public function serial(): BelongsTo
    {
        return $this->belongsTo(MaterialSerial::class, 'material_serial_id');
    }
}
