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

        Material::updateOrCreate(
            [
                'category' => 'equipment',
                'type' => 'router',
                'model' => 'Router Legacy N300',
            ],
            [
                'is_serialized' => true,
                'is_active' => false,
            ]
        );

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

        $otros = [
            ['type' => 'kit herramientas', 'model' => 'Toolbox V1', 'is_serialized' => false],
            ['type' => 'carteleria', 'model' => null, 'is_serialized' => false],
        ];

        foreach ($otros as $item) {
            Material::updateOrCreate(
                [
                    'category' => 'other',
                    'type' => $item['type'],
                    'model' => $item['model'],
                ],
                [
                    'is_serialized' => $item['is_serialized'],
                    'is_active' => true,
                ]
            );
        }

        if (Material::where('category', 'acometida')->doesntExist()) {
            Material::factory()->acometida()->count(3)->create();
        }

        if (Material::where('category', 'roseta')->doesntExist()) {
            Material::factory()->roseta()->count(2)->create();
        }

        if (Material::where('category', 'other')->where('is_active', false)->doesntExist()) {
            Material::factory()->other()->inactive()->create();
        }
    }
}
