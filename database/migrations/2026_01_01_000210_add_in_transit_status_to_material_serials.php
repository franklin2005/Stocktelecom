<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE material_serials
            MODIFY status ENUM('available', 'reserved', 'assigned', 'installed', 'lost', 'scrapped', 'in_transit')
            NOT NULL DEFAULT 'available'
        ");
    }

    public function down(): void
    {
        DB::table('material_serials')
            ->where('status', 'in_transit')
            ->update(['status' => 'assigned']);

        DB::statement("
            ALTER TABLE material_serials
            MODIFY status ENUM('available', 'reserved', 'assigned', 'installed', 'lost', 'scrapped')
            NOT NULL DEFAULT 'available'
        ");
    }
};
