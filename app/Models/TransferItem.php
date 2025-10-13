<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferItem extends Model
{
    protected $fillable = [
        'transfer_id',
        'material_id',
        'quantity',
        'material_serial_id',
    ];

    protected $casts = [
        'transfer_id' => 'integer',
        'material_id' => 'integer',
        'material_serial_id' => 'integer',
        'quantity' => 'integer',
    ];

    /**
     * Parent transfer record.
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * Material being transferred.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Serialized material, if applicable.
     */
    public function serial(): BelongsTo
    {
        return $this->belongsTo(MaterialSerial::class, 'material_serial_id');
    }
}
