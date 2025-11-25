<?php

namespace App\Services\Admin\Staff;

use App\Models\User;

class StaffService
{
    public function __construct(
        protected StaffCreator $creator,
        protected StaffUpdater $updater,
        protected StaffDeleter $deleter,
        protected StaffHelper $helper,
    ) {
    }

    /**
     * Crear usuario staff (admin / logistics / super_admin).
     */
    public function createStaff(array $data, User $actor): User
    {
        return $this->creator->create($data, $actor);
    }

    /**
     * Actualizar usuario staff.
     */
    public function updateStaff(User $staff, array $data, User $actor): User
    {
        return $this->updater->update($staff, $data, $actor);
    }

    /**
     * Eliminar usuario staff.
     */
    public function deleteStaff(User $staff, User $actor): void
    {
        $this->deleter->delete($staff, $actor);
    }

    public function roleLabel(string $role): string
    {
        return $this->helper->roleLabel($role);
    }

    /** Lo usamos desde el controlador para decidir la pestaña de redireccion */
    public function tabForRole(string $role): string
    {
        return $this->helper->tabForRole($role);
    }
}
