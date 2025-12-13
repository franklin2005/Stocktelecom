<?php

namespace App\Services\Admin\Staff;

use App\Models\Inventory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffDeleter
{
    public function __construct(
        protected StaffRoleValidator $roleValidator,
        protected StaffStockManager $stockManager,
        protected StaffActionLogger $actionLogger,
        protected StaffHelper $helper,
    ) {
    }
    // eliminar usuario de personal administrativo
    public function delete(User $staff, User $actor): void
    {   // verificar que el usuario es de personal administrativo
        if (! $this->roleValidator->isStaffRole($staff->role)) {
            abort(404);
        }
        // validar permisos para eliminar usuario
        $this->roleValidator->assertCanDeleteUser($actor, $staff);
        $this->ensureNotSelfDelete($actor, $staff);
        $this->roleValidator->ensureAdminWillRemainOnDelete($staff);
        $this->roleValidator->ensureSuperAdminWillRemainOnDelete($staff);

        $location = null;
        // si es personal de logistica, asegurar que no tenga stock asignado
        if ($staff->role === 'logistics') {
            $location = $staff->stockLocation()->first();
            $this->stockManager->ensureLogisticsHasNoStock($location);
        }
        // obtener datos para el log
        $staffName  = $staff->name;
        $staffEmail = $staff->email;
        $roleLabel  = $this->helper->roleLabel($staff->role);
        // eliminar usuario en transaccion
        DB::transaction(function () use ($staff, $actor, $staffName, $staffEmail, $roleLabel, $location) {
            $this->actionLogger->logUserAction(
                actorId: $actor->id,
                targetId: $staff->id,
                action: 'deleted',
                details: 'Eliminacion de usuario ' . $roleLabel . ': ' . $staffName . ' (' . $staffEmail . ')'
            );
            // si es personal de logistica, eliminar inventario y ubicacion de stock
            if ($staff->role === 'logistics' && $location) {
                Inventory::query()
                    ->where('location_id', $location->id)
                    ->delete();
                // eliminar ubicacion de stock
                $location->delete();
            }

            $staff->delete();
        });
    }

    protected function ensureNotSelfDelete(User $actor, User $staff): void
    {
        if ($actor->id === $staff->id) {
            throw ValidationException::withMessages([
                'general' => 'No puedes eliminar tu propio usuario.',
            ])->errorBag('deleteStaff')->redirectTo(
                route('admin.personnel', ['tab' => $this->helper->tabForRole($staff->role)])
            );
        }
    }
}
