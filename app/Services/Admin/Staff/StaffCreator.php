<?php

namespace App\Services\Admin\Staff;

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
    // crear nuevo usuario de personal administrativo
    public function create(array $data, User $actor): User
    {   // validar permisos para asignar el rol
        $this->roleValidator->assertCanAssignRole($actor, $data['role']);
        // obtener etiqueta del rol
        $roleLabel = $this->helper->roleLabel($data['role']);
        // crear usuario
        return DB::transaction(function () use ($data, $actor, $roleLabel) {
            $staffUser = User::create([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'password'  => Hash::make($data['password']),
                'role'      => $data['role'],
                'tech_code' => null,
            ]);
            // sincronizar ubicacion de stock si es personal de logistica
            if ($data['role'] === 'logistics') {
                $this->stockManager->syncLogisticsLocation($staffUser, $data['name']);
            }
            // registrar accion de creacion
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
