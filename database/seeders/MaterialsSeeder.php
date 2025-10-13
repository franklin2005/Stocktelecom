<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;

class MaterialsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $equipment = [
            ['type' => 'router', 'model' => 'Router ZX-1000'],
            ['type' => 'router', 'model' => 'Router FiberPro'],
            ['type' => 'ont', 'model' => 'ONT GigaLite'],
            ['type' => 'ont', 'model' => 'ONT Xtreme'],
            ['type' => 'decodificador', 'model' => 'Decoder HD Plus'],
            ['type' => 'mando', 'model' => 'Remote Wave'],
        ];

        foreach ($equipment as $item) {
            Material::updateOrCreate(
                [
                    'category' => 'equipment',
                    'type' => $item['type'],
                    'model' => $item['model'],
                ],
                [
                    'is_serialized' => true,
                    'is_active' => true,
                ]
            );
        }

        $acometidas = [
            'ZTE',
            'Huawei',
            'Corning',
            '3M',
            'Mixta',
            'Interior',
        ];

        foreach ($acometidas as $type) {
            Material::updateOrCreate(
                [
                    'category' => 'acometida',
                    'type' => $type,
                    'model' => null,
                ],
                [
                    'is_serialized' => false,
                    'is_active' => true,
                ]
            );
        }

        $rosetas = [
            'final',
            'transicion',
        ];

        foreach ($rosetas as $type) {
            Material::updateOrCreate(
                [
                    'category' => 'roseta',
                    'type' => $type,
                    'model' => null,
                ],
                [
                    'is_serialized' => false,
                    'is_active' => true,
                ]
            );
        }
    }
}
