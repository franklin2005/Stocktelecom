<?php

namespace Database\Seeders;

use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Super Administrador',
                'email' => 'superadmin@inventario.local',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'tech_code' => null,
            ],
            [
                'name' => 'Administrador General',
                'email' => 'admin@inventario.local',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'tech_code' => null,
            ],
            [
                'name' => 'Coordinador Logistica',
                'email' => 'logistica@inventario.local',
                'password' => Hash::make('password'),
                'role' => 'logistics',
                'tech_code' => null,
            ],
            [
                'name' => 'Tecnico Juan Perez',
                'email' => 'juan@inventario.local',
                'password' => Hash::make('password'),
                'role' => 'technician',
                'tech_code' => 'TECH-JP-001',
            ],
            [
                'name' => 'Tecnico Maria Gomez',
                'email' => 'maria@inventario.local',
                'password' => Hash::make('password'),
                'role' => 'technician',
                'tech_code' => 'TECH-MG-002',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            if (in_array($user->role, ['technician', 'logistics'], true)) {
                StockLocation::updateOrCreate(
                    [
                        'location_type' => 'user',
                        'ref_id' => $user->id,
                    ],
                    [
                        'name' => 'Stock de ' . $user->name,
                    ]
                );
            }
        }
    }
}
