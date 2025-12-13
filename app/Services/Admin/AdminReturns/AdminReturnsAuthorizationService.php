<?php

namespace App\Services\Admin\AdminReturns;

use App\Models\User;

class AdminReturnsAuthorizationService
{   // verificar si el usuario puede gestionar devoluciones
    public function canManageReturns(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'logistics'], true);
    }
    //doble check de la autorizacion para gestionar devoluciones
    public function ensureCanManageReturns(User $user): void
    {
        if (! $this->canManageReturns($user)) {
            abort(403);
        }
    }
}
