<?php

namespace App\Services\Admin\Staff;

use App\Models\UserActionLog;

class StaffActionLogger
{   // registrar accion de un usuario sobre otro
    public function logUserAction(int $actorId, int $targetId, string $action, string $details): void
    {   // registrar accion del usuario en el log
        UserActionLog::create([
            'actor_id' => $actorId,
            'target_id' => $targetId,
            'action' => $action,
            'details' => $details,
        ]);
    }
}
