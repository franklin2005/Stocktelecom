<?php

namespace App\Services\Admin\Material;

use App\Models\Material;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AdminMaterialAuthorizationService
{   // verificar si el usuario puede gestionar el almacen
    public function canManageWarehouse(): bool
    {// obtener rol del usuario autenticado
        $role = Auth::user()?->role;
        // verificar si el rol es logistica o super admin
        return in_array($role, ['super_admin', 'logistics'], true);
    }
    // doble check de la autorizacion para gestionar el almacen
    public function ensureCanManageWarehouse(): void
    { // si no tiene permiso, abortar con 403
        if (! $this->canManageWarehouse()) {
            abort(403);
        }
    }
}
