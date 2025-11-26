<?php

namespace App\Services\Technician\TechnicianWorkOrder;

use App\Models\User;
use App\Models\WorkOrder;
use RuntimeException;

class TechnicianWorkOrderCreationService
{
    public function createOpenOrder(User $technician, string $orderNumber, ?string $notes): WorkOrder
    {
        $existingOpenOrder = WorkOrder::query()
            ->where('technician_id', $technician->id)
            ->where('status', 'open')
            ->exists();

        if ($existingOpenOrder) {
            throw new RuntimeException('Ya cuentas con una orden abierta. Confirma o cancela antes de crear una nueva.');
        }

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
