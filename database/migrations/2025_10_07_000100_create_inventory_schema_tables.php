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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->enum('location_type', ['warehouse', 'user'])->index();
            $table->unsignedBigInteger('ref_id')->nullable()->index();
            $table->string('name');
            $table->timestamps();

            $table->unique(['location_type', 'ref_id']);
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->enum('category', ['equipment', 'acometida', 'roseta'])->index();
            $table->string('type');
            $table->string('model')->nullable();
            $table->boolean('is_serialized')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('material_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('serial_number')->unique();
            $table->enum('status', ['available', 'assigned', 'installed', 'lost', 'scrapped'])->default('available')->index();
            $table->foreignId('current_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['location_id', 'material_id']);
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('from_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('initiator_user_id')->constrained('users')->restrictOnDelete();
            $table->boolean('requires_receiver_accept')->default(true);
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled'])->default('pending')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('transfers')->restrictOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->unsignedInteger('quantity')->nullable();
            $table->foreignId('material_serial_id')->nullable()->constrained('material_serials')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('technician_id')->constrained('users')->restrictOnDelete();
            $table->string('technician_code');
            $table->string('technician_name');
            $table->enum('status', ['open', 'confirmed', 'cancelled'])->default('open')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('work_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->restrictOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->unsignedInteger('quantity')->nullable();
            $table->foreignId('material_serial_id')->nullable()->constrained('material_serials')->restrictOnDelete();
            $table->timestamps();
        });

        $this->addQuantitySerialConstraint('transfer_items');
        $this->addQuantitySerialConstraint('work_order_items');

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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');

        $this->dropQuantitySerialConstraint('work_order_items');
        Schema::dropIfExists('work_order_items');
        Schema::dropIfExists('work_orders');

        $this->dropQuantitySerialConstraint('transfer_items');
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('material_serials');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('stock_locations');
        Schema::dropIfExists('warehouses');
    }

    protected function addQuantitySerialConstraint(string $table): void
    {
        $driver = $this->databaseDriver();

        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER {$table}_quantity_serial_insert BEFORE INSERT ON {$table} FOR EACH ROW BEGIN
                SELECT CASE
                    WHEN ((NEW.quantity IS NOT NULL AND NEW.material_serial_id IS NULL) OR (NEW.quantity IS NULL AND NEW.material_serial_id IS NOT NULL))
                        THEN 0
                    ELSE RAISE(ABORT, 'quantity_or_serial_required')
                END;
            END;");

            DB::unprepared("CREATE TRIGGER {$table}_quantity_serial_update BEFORE UPDATE ON {$table} FOR EACH ROW BEGIN
                SELECT CASE
                    WHEN ((NEW.quantity IS NOT NULL AND NEW.material_serial_id IS NULL) OR (NEW.quantity IS NULL AND NEW.material_serial_id IS NOT NULL))
                        THEN 0
                    ELSE RAISE(ABORT, 'quantity_or_serial_required')
                END;
            END;");
        } else {
            $constraintName = "{$table}_quantity_serial_chk";
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraintName} CHECK ((quantity IS NOT NULL AND material_serial_id IS NULL) OR (quantity IS NULL AND material_serial_id IS NOT NULL))");
        }
    }

    protected function dropQuantitySerialConstraint(string $table): void
    {
        $driver = $this->databaseDriver();

        if ($driver === 'sqlite') {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_quantity_serial_insert;");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_quantity_serial_update;");
        } else {
            $constraintName = "{$table}_quantity_serial_chk";
            try {
                DB::statement("ALTER TABLE {$table} DROP CHECK {$constraintName}");
            } catch (\Throwable $e) {
                // Some database drivers drop the check automatically with the table.
            }
        }
    }

    protected function databaseDriver(): string
    {
        $connection = $this->getConnection();

        return Schema::connection($connection)->getConnection()->getDriverName();
    }
};
