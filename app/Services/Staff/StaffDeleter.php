<?php

namespace App\Services\Staff;

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

    public function delete(User $staff, User $actor): void
    {
        if (! $this->roleValidator->isStaffRole($staff->role)) {
            abort(404);
        }

        $this->roleValidator->assertCanDeleteUser($actor, $staff);
        $this->ensureNotSelfDelete($actor, $staff);
        $this->roleValidator->ensureAdminWillRemainOnDelete($staff);
        $this->roleValidator->ensureSuperAdminWillRemainOnDelete($staff);

        $location = null;

        if ($staff->role === 'logistics') {
            $location = $staff->stockLocation()->first();
            $this->stockManager->ensureLogisticsHasNoStock($location);
        }

        $staffName  = $staff->name;
        $staffEmail = $staff->email;
        $roleLabel  = $this->helper->roleLabel($staff->role);

        DB::transaction(function () use ($staff, $actor, $staffName, $staffEmail, $roleLabel, $location) {
            $this->actionLogger->logUserAction(
                actorId: $actor->id,
                targetId: $staff->id,
                action: 'deleted',
                details: 'Eliminacion de usuario ' . $roleLabel . ': ' . $staffName . ' (' . $staffEmail . ')'
            );

            if ($staff->role === 'logistics' && $location) {
                Inventory::query()
                    ->where('location_id', $location->id)
                    ->delete();

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
