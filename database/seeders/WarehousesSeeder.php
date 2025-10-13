<?php

namespace Database\Seeders;

use App\Models\StockLocation;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehousesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Almacen Principal']
        );

        StockLocation::firstOrCreate(
            [
                'location_type' => 'warehouse',
                'ref_id' => $warehouse->id,
            ],
            [
                'name' => 'Almacen MAIN',
            ]
        );
    }
}
