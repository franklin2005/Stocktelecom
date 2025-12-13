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
        string $movementType,// 'in' o 'out'
        Material $material, // material asociado
        ?MaterialSerial $serial, // serial asociado, si aplica
        ?StockLocation $from, // ubicacion origen
        ?StockLocation $to, // ubicacion origen y destino
        int $quantity, // cantidad movida
        string $referenceType, // clase del modelo de referencia
        int $referenceId, // ID del modelo de referencia
        ?int $performedBy = null, // ID del usuario que realiza la accion
    ): StockMovement { // crear y retornar registro de movimiento
        return StockMovement::create([
            'movement_type' => $movementType,//'in' o 'out'
            'material_id' => $material->id,// material asociado
            'material_serial_id' => $serial?->id,// serial asociado, si aplica
            'from_location_id' => $from?->id,// ubicacion origen
            'to_location_id' => $to?->id,// ubicacion origen y destino
            'quantity' => $quantity,// cantidad movida
            'reference_type' => $referenceType,// clase del modelo de referencia
            'reference_id' => $referenceId,// ID del modelo de referencia
            'performed_at' => now(),// fecha y hora del movimiento
            'performed_by' => $performedBy ?? Auth::id(),// ID del usuario que realiza la accion
        ]);
    }
}
