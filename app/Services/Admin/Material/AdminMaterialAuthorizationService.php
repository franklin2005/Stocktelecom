<?php

namespace App\Services\Admin\Material;

use App\Models\Material;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AdminMaterialAuthorizationService
{
    public function canManageWarehouse(): bool
    {
        $role = Auth::user()?->role;

        return in_array($role, ['super_admin', 'logistics'], true);
    }

    public function ensureCanManageWarehouse(): void
    {
        if (! $this->canManageWarehouse()) {
            abort(403);
        }
    }
}
