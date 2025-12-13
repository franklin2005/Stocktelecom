<?php

namespace App\Services\Admin\Staff;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class StaffRoleValidator
{ // verificar si el rol es de personal administrativo
    public function isStaffRole(string $role): bool
    {
        return in_array($role, ['admin', 'logistics', 'super_admin'], true);
    }
// validar permisos para asignar un rol
    public function assertCanAssignRole(User $actor, string $role): void
    {// solo super admin puede asignar roles de admin y super admin
        if (in_array($role, ['admin', 'super_admin'], true) && $actor->role !== 'super_admin') {
            abort(403);
        }
    }
    // validar permisos para gestionar el rol de un usuario
    public function assertCanManageUserRole(User $actor, User $staff): void
    {   // solo super admin puede gestionar roles de admin y super admin
        if (in_array($staff->role, ['admin', 'super_admin'], true) && $actor->role !== 'super_admin') {
            abort(403);
        }
    }
    // validar permisos para eliminar un usuario
    public function assertCanDeleteUser(User $actor, User $staff): void
    {
        if (in_array($staff->role, ['admin', 'super_admin'], true) && $actor->role !== 'super_admin') {
            abort(403);
        }
    }
    // asegurar que no se elimine a si mismo
    public function ensureAdminWillRemain(User $staff, string $newRole): void
    {   // si el rol actual es admin y se cambia a otro rol
        if ($staff->role === 'admin' && $newRole !== 'admin') {
            $otherAdmins = User::admins()
                ->where('id', '!=', $staff->id)
                ->count();
            // asegurar que quede al menos un admin
            if ($otherAdmins === 0) {
                throw ValidationException::withMessages([
                    'role' => 'Debe quedar al menos un administrador activo en el sistema.',
                ])->errorBag('updateStaff')->redirectTo(
                    route('admin.personnel', ['tab' => 'admins', 'edit_admin' => $staff->id])
                );
            }
        }
    }
    // asegurar que quede al menos un super admin
    public function ensureSuperAdminWillRemain(User $staff, string $newRole): void
    {   // si el rol actual es super admin y se cambia a otro rol
        if ($staff->role === 'super_admin' && $newRole !== 'super_admin') {
            $otherSuperAdmins = User::superAdmins()
                ->where('id', '!=', $staff->id)
                ->count();
            // asegurar que quede al menos un super admin
            if ($otherSuperAdmins === 0) {
                throw ValidationException::withMessages([
                    'role' => 'Debe quedar al menos un super administrador activo en el sistema.',
                ])->errorBag('updateStaff')->redirectTo(
                    route('admin.personnel', ['tab' => 'super_admins', 'edit_super_admin' => $staff->id])
                );
            }
        }
    }
    // asegurar que quede al menos un admin al eliminar usuario
    public function ensureAdminWillRemainOnDelete(User $staff): void
    {   // si el rol no es admin, retornar
        if ($staff->role !== 'admin') {
            return;
        }
        // contar otros administradores activos
        $otherAdmins = User::admins()
            ->where('id', '!=', $staff->id)
            ->count();
        // si no hay otros, lanzar error
        if ($otherAdmins === 0) {
            throw ValidationException::withMessages([
                'general' => 'Debe quedar al menos un administrador activo en el sistema.',
            ])->errorBag('deleteStaff')->redirectTo(
                route('admin.personnel', ['tab' => 'admins'])
            );
        }
    }
    // asegurar que quede al menos un super admin al eliminar usuario
    public function ensureSuperAdminWillRemainOnDelete(User $staff): void
    {   // si el rol no es super admin, retornar
        if ($staff->role !== 'super_admin') {
            return;
        }
        // contar otros super administradores activos
        $otherSuperAdmins = User::superAdmins()
            ->where('id', '!=', $staff->id)
            ->count();
        // si no hay otros, lanzar error
        if ($otherSuperAdmins === 0) {
            throw ValidationException::withMessages([
                'general' => 'Debe quedar al menos un super administrador activo en el sistema.',
            ])->errorBag('deleteStaff')->redirectTo(
                route('admin.personnel', ['tab' => 'super_admins'])
            );
        }
    }
}
