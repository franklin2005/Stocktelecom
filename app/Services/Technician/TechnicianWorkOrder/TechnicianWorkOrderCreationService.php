<?php

namespace App\Services\Technician\TechnicianWorkOrder;

use App\Models\User;
use App\Models\WorkOrder;
use RuntimeException;
// Servicio para crear ordenes de trabajo de tecnicos
class TechnicianWorkOrderCreationService
{   // crear nueva orden de trabajo abierta para el tecnico
    public function createOpenOrder(User $technician, string $orderNumber, ?string $notes): WorkOrder
    {   // verificar si ya existe una orden abierta para el tecnico
        $existingOpenOrder = WorkOrder::query()
            ->where('technician_id', $technician->id)
            ->where('status', 'open')
            ->exists();
        // si existe, lanzar error, solo puede haber una orden abierta a la vez
        if ($existingOpenOrder) {
            throw new RuntimeException('Ya cuentas con una orden abierta. Confirma o cancela antes de crear una nueva.');
        }
        // crear y retornar nueva orden de trabajo
        return WorkOrder::create([
            'order_number' => $orderNumber,
            'technician_id' => $technician->id,
            'technician_code' => $technician->tech_code ?: ('TEC-' . $technician->id),
            'technician_name' => $technician->name,
            'status' => 'open',
            'notes' => $notes,
            'notes_author_type' => $notes ? 'technician' : null,
            'notes_author_name' => $notes ? $technician->name : null,
        ]);
    }
}
