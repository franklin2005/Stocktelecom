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

    public function update(User $staff, array $data, User $actor): User
    {
        if (! $this->roleValidator->isStaffRole($staff->role)) {
            abort(404);
        }

        $this->roleValidator->assertCanManageUserRole($actor, $staff);
        $this->roleValidator->assertCanAssignRole($actor, $data['role']);

        $previousRole = $staff->role;
        $original     = $staff->only(['name', 'email', 'role']);

        $this->roleValidator->ensureAdminWillRemain($staff, $data['role']);
        $this->roleValidator->ensureSuperAdminWillRemain($staff, $data['role']);

        $payload = [
            'name'  => $data['name'],
            'email' => $data['email'],
            'role'  => $data['role'],
        ];

        $passwordChanged = false;

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
            $passwordChanged = true;
        }

        $fieldsChanged = $this->helper->detectChangedFields($original, $payload, $passwordChanged);
        $newRoleLabel  = $this->helper->roleLabel($data['role']);

        return DB::transaction(function () use ($staff, $payload, $previousRole, $actor, $fieldsChanged, $newRoleLabel) {
            $staff->update($payload);

            $this->stockManager->handleLogisticsLocationOnUpdate(
                $staff,
                $previousRole,
                $payload['role'],
                $payload['name']
            );

            $details = $fieldsChanged
                ? 'Actualizacion de usuario (' . $newRoleLabel . '). Campos modificados: ' . implode(', ', $fieldsChanged)
                : 'Actualizacion de usuario sin cambios en los datos principales.';

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
