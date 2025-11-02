<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->enum('type', ['transfer', 'return'])->default('transfer')->after('order_number')->index();
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
