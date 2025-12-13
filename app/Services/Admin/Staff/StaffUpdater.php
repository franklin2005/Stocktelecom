<?php

namespace App\Services\Admin\Staff;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StaffUpdater
{
    public function __construct(
        protected StaffRoleValidator $roleValidator,
        protected StaffStockManager $stockManager,
        protected StaffActionLogger $actionLogger,
        protected StaffHelper $helper,
    ) {
    }
    // actualizar datos de un usuario de personal administrativo
    public function update(User $staff, array $data, User $actor): User
    {
        if (! $this->roleValidator->isStaffRole($staff->role)) {
            abort(404);
        }
        // validar permisos para gestionar el rol del usuario
        $this->roleValidator->assertCanManageUserRole($actor, $staff);
        $this->roleValidator->assertCanAssignRole($actor, $data['role']);
        // guardar rol previo y datos originales
        $previousRole = $staff->role;
        $original     = $staff->only(['name', 'email', 'role']);
        // asegurar que quede al menos un admin y super admin
        $this->roleValidator->ensureAdminWillRemain($staff, $data['role']);
        $this->roleValidator->ensureSuperAdminWillRemain($staff, $data['role']);
        // preparar datos para actualizacion
        $payload = [
            'name'  => $data['name'],
            'email' => $data['email'],
            'role'  => $data['role'],
        ];
        // verificar si se cambio la contraseña
        $passwordChanged = false;
        // si se proporciono una nueva contraseña, actualizarla
        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
            $passwordChanged = true;
        }
        // detectar campos que cambiaron
        $fieldsChanged = $this->helper->detectChangedFields($original, $payload, $passwordChanged);
        $newRoleLabel  = $this->helper->roleLabel($data['role']);
        // actualizar usuario en transaccion
        return DB::transaction(function () use ($staff, $payload, $previousRole, $actor, $fieldsChanged, $newRoleLabel) {
            $staff->update($payload);
            // manejar ubicacion de stock si cambio el rol
            $this->stockManager->handleLogisticsLocationOnUpdate(
                $staff,
                $previousRole,
                $payload['role'],
                $payload['name']
            );
            // registrar accion de actualizacion
            $details = $fieldsChanged
                ? 'Actualizacion de usuario (' . $newRoleLabel . '). Campos modificados: ' . implode(', ', $fieldsChanged)
                : 'Actualizacion de usuario sin cambios en los datos principales.';
                // registrar accion
            $this->actionLogger->logUserAction(
                actorId: $actor->id,
                targetId: $staff->id,
                action: 'updated',
                details: $details
            );

            return $staff;
        });
    }
}
