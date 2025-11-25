<?php

namespace App\Services\Admin\Staff;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class StaffRoleValidator
{
    public function isStaffRole(string $role): bool
    {
        return in_array($role, ['admin', 'logistics', 'super_admin'], true);
    }

    public function assertCanAssignRole(User $actor, string $role): void
    {
        if (in_array($role, ['admin', 'super_admin'], true) && $actor->role !== 'super_admin') {
            abort(403);
        }
    }

    public function assertCanManageUserRole(User $actor, User $staff): void
    {
        if (in_array($staff->role, ['admin', 'super_admin'], true) && $actor->role !== 'super_admin') {
            abort(403);
        }
    }

    public function assertCanDeleteUser(User $actor, User $staff): void
    {
        if (in_array($staff->role, ['admin', 'super_admin'], true) && $actor->role !== 'super_admin') {
            abort(403);
        }
    }

    public function ensureAdminWillRemain(User $staff, string $newRole): void
    {
        if ($staff->role === 'admin' && $newRole !== 'admin') {
            $otherAdmins = User::admins()
                ->where('id', '!=', $staff->id)
                ->count();

            if ($otherAdmins === 0) {
                throw ValidationException::withMessages([
                    'role' => 'Debe quedar al menos un administrador activo en el sistema.',
                ])->errorBag('updateStaff')->redirectTo(
                    route('admin.personnel', ['tab' => 'admins', 'edit_admin' => $staff->id])
                );
            }
        }
    }

    public function ensureSuperAdminWillRemain(User $staff, string $newRole): void
    {
        if ($staff->role === 'super_admin' && $newRole !== 'super_admin') {
            $otherSuperAdmins = User::superAdmins()
                ->where('id', '!=', $staff->id)
                ->count();

            if ($otherSuperAdmins === 0) {
                throw ValidationException::withMessages([
                    'role' => 'Debe quedar al menos un super administrador activo en el sistema.',
                ])->errorBag('updateStaff')->redirectTo(
                    route('admin.personnel', ['tab' => 'super_admins', 'edit_super_admin' => $staff->id])
                );
            }
        }
    }

    public function ensureAdminWillRemainOnDelete(User $staff): void
    {
        if ($staff->role !== 'admin') {
            return;
        }

        $otherAdmins = User::admins()
            ->where('id', '!=', $staff->id)
            ->count();

        if ($otherAdmins === 0) {
            throw ValidationException::withMessages([
                'general' => 'Debe quedar al menos un administrador activo en el sistema.',
            ])->errorBag('deleteStaff')->redirectTo(
                route('admin.personnel', ['tab' => 'admins'])
            );
        }
    }

    public function ensureSuperAdminWillRemainOnDelete(User $staff): void
    {
        if ($staff->role !== 'super_admin') {
            return;
        }

        $otherSuperAdmins = User::superAdmins()
            ->where('id', '!=', $staff->id)
            ->count();

        if ($otherSuperAdmins === 0) {
            throw ValidationException::withMessages([
                'general' => 'Debe quedar al menos un super administrador activo en el sistema.',
            ])->errorBag('deleteStaff')->redirectTo(
                route('admin.personnel', ['tab' => 'super_admins'])
            );
        }
    }
}
