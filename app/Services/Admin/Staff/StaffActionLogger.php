<?php

namespace App\Services\Admin\Staff;

use App\Models\UserActionLog;

class StaffActionLogger
{
    public function logUserAction(int $actorId, int $targetId, string $action, string $details): void
    {
        UserActionLog::create([
            'actor_id' => $actorId,
            'target_id' => $targetId,
            'action' => $action,
            'details' => $details,
        ]);
    }
}
