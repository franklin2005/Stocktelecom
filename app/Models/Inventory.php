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
     * Location the inventory record belongs to.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    /**
     * Material tracked in the inventory record.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
