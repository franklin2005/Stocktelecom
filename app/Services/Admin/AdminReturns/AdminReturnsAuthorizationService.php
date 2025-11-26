<?php

namespace App\Services\Admin\AdminReturns;

use App\Models\User;

class AdminReturnsAuthorizationService
{
    public function canManageReturns(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'logistics'], true);
    }

    public function ensureCanManageReturns(User $user): void
    {
        if (! $this->canManageReturns($user)) {
            abort(403);
        }
    }
}
