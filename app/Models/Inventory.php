<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    protected $fillable = [
        'location_id',
        'material_id',
        'quantity',
    ];

    protected $casts = [
        'location_id' => 'integer',
        'material_id' => 'integer',
        'quantity' => 'integer',
    ];

    /**
     * ubicación del inventario.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    /**
     * material del inventario.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
