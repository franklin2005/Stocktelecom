<?php

namespace App\Services\Technician\TechnicianWorkOrder;

use App\Models\User;
use App\Models\WorkOrder;

class TechnicianWorkOrderAuthorizationService
{
    public function assertOwnsOpenOrder(WorkOrder $workOrder, User $technician): void
    {
        if (
            $workOrder->technician_id !== $technician->id ||
            $workOrder->status !== 'open'
        ) {
            abort(403);
        }
    }
}
