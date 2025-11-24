<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->enum('location_type', ['warehouse', 'user'])->index();
            $table->unsignedBigInteger('ref_id')->nullable()->index();
            $table->string('name');
            $table->timestamps();

            $table->unique(['location_type', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_locations');
    }
};
