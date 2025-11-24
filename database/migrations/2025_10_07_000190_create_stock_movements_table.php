<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->enum('movement_type', ['transfer_in', 'transfer_out', 'consumption', 'adjustment'])->index();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('material_serial_id')->nullable()->constrained('material_serials')->restrictOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->enum('reference_type', ['transfer', 'work_order', 'manual_adjustment'])->index();
            $table->unsignedBigInteger('reference_id')->index();
            $table->timestamp('performed_at')->useCurrent();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['performed_at', 'movement_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
