<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('work_orders', 'notes_author_type')) {
                $table->string('notes_author_type', 20)->nullable()->after('notes');
            }

            if (! Schema::hasColumn('work_orders', 'notes_author_name')) {
                $table->string('notes_author_name', 255)->nullable()->after('notes_author_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            if (Schema::hasColumn('work_orders', 'notes_author_name')) {
                $table->dropColumn('notes_author_name');
            }

            if (Schema::hasColumn('work_orders', 'notes_author_type')) {
                $table->dropColumn('notes_author_type');
            }
        });
    }
};
