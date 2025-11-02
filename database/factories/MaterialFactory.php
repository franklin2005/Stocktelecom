<?php

namespace Database\Factories;

use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    private const EQUIPMENT_MODELS = [
        'router' => [
            'Router ZX-1000',
            'Router FiberPro',
            'Router NovaWave',
        ],
        'ont' => [
            'ONT GigaLite',
            'ONT Xtreme',
            'ONT FiberMax',
        ],
        'decodificador' => [
            'Decoder HD Plus',
            'Decoder Ultra 4K',
        ],
        'mando' => [
            'Remote Wave',
            'Remote Air Lite',
        ],
    ];

    private const ACOMETIDA_TYPES = [
        'ZTE',
        'Huawei',
        'Corning',
        '3M',
        'Mixta',
        'Interior',
    ];

    private const ROSETA_TYPES = [
        'final',
        'transicion',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->equipmentAttributes();
    }

    /**
     * Equipo serializado.
     */
    public function equipment(): static
    {
        return $this->state(fn () => $this->equipmentAttributes());
    }

    /**
     * Acometida por cantidad.
     */
    public function acometida(): static
    {
        return $this->state(function () {
            return [
                'category' => 'acometida',
                'type' => $this->faker->randomElement(self::ACOMETIDA_TYPES),
                'model' => null,
                'is_serialized' => false,
                'is_active' => true,
            ];
        });
    }

    /**
     * Roseta por cantidad.
     */
    public function roseta(): static
    {
        return $this->state(function () {
            return [
                'category' => 'roseta',
                'type' => $this->faker->randomElement(self::ROSETA_TYPES),
                'model' => null,
                'is_serialized' => false,
                'is_active' => true,
            ];
        });
    }

    /**
     * Otro tipo de material.
     */
    public function other(): static
    {
        return $this->state(function () {
            return [
                'category' => 'other',
                'type' => $this->faker->unique()->words(2, true),
                'model' => $this->faker->optional()->bothify('Modelo-###'),
                'is_serialized' => false,
                'is_active' => true,
            ];
        });
    }

    /**
     * Estado inactivo.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Default attributes for equipment items.
     *
     * @return array<string, mixed>
     */
    protected function equipmentAttributes(): array
    {
        $type = $this->faker->randomElement(array_keys(self::EQUIPMENT_MODELS));
        $model = $this->faker->unique()->randomElement(self::EQUIPMENT_MODELS[$type]);

        return [
            'category' => 'equipment',
            'type' => $type,
            'model' => $model,
            'is_serialized' => true,
            'is_active' => true,
        ];
    }
}
