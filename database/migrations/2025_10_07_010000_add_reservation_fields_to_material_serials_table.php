<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('material_serials', function (Blueprint $table) {
            $table->foreignId('reserved_by_user_id')
                ->nullable()
                ->after('current_location_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reserved_at')
                ->nullable()
                ->after('reserved_by_user_id');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE material_serials MODIFY status ENUM('available', 'reserved', 'assigned', 'installed', 'lost', 'scrapped') DEFAULT 'available'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TYPE material_serials_status_enum ADD VALUE IF NOT EXISTS 'reserved'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE material_serials MODIFY status ENUM('available', 'assigned', 'installed', 'lost', 'scrapped') DEFAULT 'available'");
        }

        Schema::table('material_serials', function (Blueprint $table) {
            $table->dropForeign(['reserved_by_user_id']);
            $table->dropColumn(['reserved_by_user_id', 'reserved_at']);
        });
    }
};

