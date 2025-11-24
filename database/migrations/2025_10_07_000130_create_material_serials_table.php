<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_serials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('material_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('serial_number')->unique();

            $table->enum('status', [
                'available',
                'reserved',
                'assigned',
                'installed',
                'lost',
                'scrapped',
            ])
            ->default('available')
            ->index();

            $table->foreignId('current_location_id')
                ->nullable()
                ->constrained('stock_locations')
                ->nullOnDelete();

            $table->foreignId('reserved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reserved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_serials');
    }
};
