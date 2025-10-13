<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE work_orders MODIFY status ENUM('open', 'confirmed', 'cancelled') DEFAULT 'open'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TYPE work_orders_status_enum ADD VALUE IF NOT EXISTS 'open'");
            DB::statement("ALTER TYPE work_orders_status_enum ADD VALUE IF NOT EXISTS 'confirmed'");
            DB::statement("ALTER TYPE work_orders_status_enum ADD VALUE IF NOT EXISTS 'cancelled'");
        } elseif ($driver === 'sqlite') {
            $this->rebuildSqliteWorkOrders(
                fromStatuses: ['draft' => 'open', 'posted' => 'confirmed'],
                defaultStatus: 'open'
            );
        }

        DB::table('work_orders')
            ->whereNotIn('status', ['open', 'confirmed', 'cancelled'])
            ->update(['status' => 'open']);
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE work_orders MODIFY status ENUM('draft', 'posted', 'cancelled') DEFAULT 'draft'");
        } elseif ($driver === 'pgsql') {
            // No se eliminan valores del enum existente en PostgreSQL.
        } elseif ($driver === 'sqlite') {
            $this->rebuildSqliteWorkOrders(
                fromStatuses: ['open' => 'draft', 'confirmed' => 'posted'],
                defaultStatus: 'draft',
                allowedStatuses: ['draft', 'posted', 'cancelled']
            );
        }

        DB::table('work_orders')
            ->where('status', 'open')
            ->update(['status' => 'draft']);

        DB::table('work_orders')
            ->where('status', 'confirmed')
            ->update(['status' => 'posted']);
    }

    protected function rebuildSqliteWorkOrders(array $fromStatuses, string $defaultStatus, ?array $allowedStatuses = null): void
    {
        if (! Schema::hasTable('work_orders')) {
            return;
        }

        $allowedStatuses ??= ['open', 'confirmed', 'cancelled'];

        Schema::disableForeignKeyConstraints();

        $tempOrdersTable = 'work_orders_tmp_' . uniqid('', false);

        try {
            DB::transaction(function () use ($fromStatuses, $defaultStatus, $allowedStatuses, $tempOrdersTable) {
                DB::statement('DROP INDEX IF EXISTS work_orders_order_number_unique');
                DB::statement('DROP INDEX IF EXISTS work_orders_status_index');

                Schema::rename('work_orders', $tempOrdersTable);

                Schema::create('work_orders', function (Blueprint $table) use ($allowedStatuses, $defaultStatus) {
                    $table->id();
                    $table->string('order_number')->unique();
                    $table->foreignId('technician_id')->constrained('users')->restrictOnDelete();
                    $table->string('technician_code');
                    $table->string('technician_name');
                    $table->enum('status', $allowedStatuses)->default($defaultStatus)->index();
                    $table->text('notes')->nullable();
                    $table->timestamps();
                });

                $records = DB::table($tempOrdersTable)->orderBy('id')->get();

                foreach ($records as $record) {
                    $status = $fromStatuses[$record->status] ?? $record->status;

                    if (! in_array($status, $allowedStatuses, true)) {
                        $status = $defaultStatus;
                    }

                    DB::table('work_orders')->insert([
                        'id' => $record->id,
                        'order_number' => $record->order_number,
                        'technician_id' => $record->technician_id,
                        'technician_code' => $record->technician_code,
                        'technician_name' => $record->technician_name,
                        'status' => $status,
                        'notes' => $record->notes,
                        'created_at' => $record->created_at,
                        'updated_at' => $record->updated_at,
                    ]);
                }

                Schema::dropIfExists($tempOrdersTable);

                $maxId = DB::table('work_orders')->max('id');

                if (! is_null($maxId)) {
                    DB::statement("UPDATE sqlite_sequence SET seq = {$maxId} WHERE name = 'work_orders'");
                }

                $this->rebuildSqliteWorkOrderItems();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    protected function rebuildSqliteWorkOrderItems(): void
    {
        if (! Schema::hasTable('work_order_items')) {
            return;
        }

        $tempItemsTable = 'work_order_items_tmp_' . uniqid('', false);

        Schema::rename('work_order_items', $tempItemsTable);

        Schema::create('work_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->restrictOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->unsignedInteger('quantity')->nullable();
            $table->foreignId('material_serial_id')->nullable()->constrained('material_serials')->restrictOnDelete();
            $table->timestamps();
        });

        $records = DB::table($tempItemsTable)->orderBy('id')->get();

        foreach ($records as $record) {
            DB::table('work_order_items')->insert([
                'id' => $record->id,
                'work_order_id' => $record->work_order_id,
                'material_id' => $record->material_id,
                'quantity' => $record->quantity,
                'material_serial_id' => $record->material_serial_id,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
            ]);
        }

        Schema::dropIfExists($tempItemsTable);

        $maxItemId = DB::table('work_order_items')->max('id');

        if (! is_null($maxItemId)) {
            DB::statement("UPDATE sqlite_sequence SET seq = {$maxItemId} WHERE name = 'work_order_items'");
        }
    }
};
