<?php

namespace App\Services\Staff;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StaffCreator
{
    public function __construct(
        protected StaffRoleValidator $roleValidator,
        protected StaffStockManager $stockManager,
        protected StaffActionLogger $actionLogger,
        protected StaffHelper $helper,
    ) {
    }

    public function create(array $data, User $actor): User
    {
        $this->roleValidator->assertCanAssignRole($actor, $data['role']);

        $roleLabel = $this->helper->roleLabel($data['role']);

        return DB::transaction(function () use ($data, $actor, $roleLabel) {
            $staffUser = User::create([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'password'  => Hash::make($data['password']),
                'role'      => $data['role'],
                'tech_code' => null,
            ]);

            if ($data['role'] === 'logistics') {
                $this->stockManager->syncLogisticsLocation($staffUser, $data['name']);
            }

            $this->actionLogger->logUserAction(
                actorId: $actor->id,
                targetId: $staffUser->id,
                action: 'created',
                details: 'Creacion de usuario ' . $roleLabel . ': ' . $staffUser->name . ' (' . $staffUser->email . ')'
            );

            return $staffUser;
        });
    }
}
