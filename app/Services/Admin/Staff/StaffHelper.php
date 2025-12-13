<?php

namespace App\Services\Admin\Staff;

class StaffHelper
{   // obtener etiqueta legible del rol
    public function roleLabel(string $role): string
    {   // mapear rol a etiqueta
        return match ($role) {
            'admin'       => 'administrador',
            'logistics'   => 'logistica',
            'super_admin' => 'super administrador',
            default       => $role,
        };
    }
    // obtener pestaña correspondiente al rol
    public function tabForRole(string $role): string
    {   // mapear rol a pestaña
        return match ($role) {
            'technician'  => 'technicians',
            'logistics'   => 'logistics',
            'admin'       => 'admins',
            'super_admin' => 'super_admins',
            default       => 'logistics',
        };
    }
    // detectar campos cambiados en la actualizacion de usuario
    public function detectChangedFields(array $original, array $payload, bool $passwordChanged): array
    {
        $fieldsChanged = [];
        // comparar campos originales con los del payload
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
