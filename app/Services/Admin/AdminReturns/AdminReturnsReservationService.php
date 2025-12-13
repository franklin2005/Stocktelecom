<?php

namespace App\Services\Admin\AdminReturns;

use App\Models\StockLocation;
use App\Models\Transfer;

class AdminReturnsReservationService
{
    public function pendingReturnReservations(StockLocation $technicianLocation): array
    {   // obtener transferencias pendientes de devolucion desde la ubicacion del tecnico
        $pendingTransfers = Transfer::query()
            ->where('type', 'return')
            ->where('status', 'pending')
            ->where('from_location_id', $technicianLocation->id)
            ->with(['items'])
            ->get();
        // preparar cantidades y seriales reservados
        $quantities = [];
        $serialIds = [];
        // procesar cada transferencia pendiente
        foreach ($pendingTransfers as $transfer) {
            foreach ($transfer->items as $item) {
                if ($item->material_serial_id) {
                    $serialIds[$item->material_serial_id] = true;
                    continue;
                }
                // acumular cantidades reservadas por material
                if ($item->material_id && $item->quantity) {
                    $key = 'quantity-' . $item->material_id;
                    $quantities[$key] = ($quantities[$key] ?? 0) + (int) $item->quantity;
                }
            }
        }

        return [
            'quantities' => $quantities,
            'serial_ids' => $serialIds,
        ];
    }
}
