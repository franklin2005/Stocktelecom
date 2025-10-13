<?php

namespace App\Services;

use App\Models\Material;
use App\Models\MaterialSerial;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;

class StockMovementLogger
{
    /**
     * Registra un movimiento de inventario.
     */
    public function log(
        string $movementType,
        Material $material,
        ?MaterialSerial $serial,
        ?StockLocation $from,
        ?StockLocation $to,
        int $quantity,
        string $referenceType,
        int $referenceId,
        ?int $performedBy = null,
    ): StockMovement {
        return StockMovement::create([
            'movement_type' => $movementType,
            'material_id' => $material->id,
            'material_serial_id' => $serial?->id,
            'from_location_id' => $from?->id,
            'to_location_id' => $to?->id,
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'performed_at' => now(),
            'performed_by' => $performedBy ?? Auth::id(),
        ]);
    }
}
