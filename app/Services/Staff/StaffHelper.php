<?php

namespace App\Services\Staff;

class StaffHelper
{
    public function roleLabel(string $role): string
    {
        return match ($role) {
            'admin'       => 'administrador',
            'logistics'   => 'logistica',
            'super_admin' => 'super administrador',
            default       => $role,
        };
    }

    public function tabForRole(string $role): string
    {
        return match ($role) {
            'technician'  => 'technicians',
            'logistics'   => 'logistics',
            'admin'       => 'admins',
            'super_admin' => 'super_admins',
            default       => 'logistics',
        };
    }

    public function detectChangedFields(array $original, array $payload, bool $passwordChanged): array
    {
        $fieldsChanged = [];

        if ($original['name'] !== $payload['name']) {
            $fieldsChanged[] = 'nombre';
        }

        if ($original['email'] !== $payload['email']) {
            $fieldsChanged[] = 'correo';
        }

        if ($original['role'] !== $payload['role']) {
            $fieldsChanged[] = 'rol';
        }

        if ($passwordChanged) {
            $fieldsChanged[] = 'contrasena';
        }

        return $fieldsChanged;
    }
}
