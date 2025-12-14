<?php

namespace App\Services\Technician\TechnicianWorkOrder;

use App\Models\User;
use App\Models\WorkOrder;
// Servicio para autorizar acciones en ordenes de trabajo de tecnicos
class TechnicianWorkOrderAuthorizationService
{   // asegurar que la orden de trabajo pertenece al tecnico y esta abierta
    public function assertOwnsOpenOrder(WorkOrder $workOrder, User $technician): void
    {
        if (// validar que la orden pertenece al tecnico y esta abierta
            $workOrder->technician_id !== $technician->id ||
            $workOrder->status !== 'open'
        ) {
            abort(403);
        }
    }
}
