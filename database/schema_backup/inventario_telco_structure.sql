-- Inventario FTTH schema dump

CREATE TABLE "cache" ("key" varchar not null, "value" text not null, "expiration" integer not null, primary key ("key"));

CREATE TABLE "cache_locks" ("key" varchar not null, "owner" varchar not null, "expiration" integer not null, primary key ("key"));

CREATE TABLE "failed_jobs" ("id" integer primary key autoincrement not null, "uuid" varchar not null, "connection" text not null, "queue" text not null, "payload" text not null, "exception" text not null, "failed_at" datetime not null default CURRENT_TIMESTAMP);

CREATE TABLE "inventories" ("id" integer primary key autoincrement not null, "location_id" integer not null, "material_id" integer not null, "quantity" integer not null default '0', "created_at" datetime, "updated_at" datetime, foreign key("location_id") references "stock_locations"("id") on delete restrict, foreign key("material_id") references "materials"("id") on delete restrict);

CREATE TABLE "job_batches" ("id" varchar not null, "name" varchar not null, "total_jobs" integer not null, "pending_jobs" integer not null, "failed_jobs" integer not null, "failed_job_ids" text not null, "options" text, "cancelled_at" integer, "created_at" integer not null, "finished_at" integer, primary key ("id"));

CREATE TABLE "jobs" ("id" integer primary key autoincrement not null, "queue" varchar not null, "payload" text not null, "attempts" integer not null, "reserved_at" integer, "available_at" integer not null, "created_at" integer not null);

CREATE TABLE "material_serials" ("id" integer primary key autoincrement not null, "material_id" integer not null, "serial_number" varchar not null, "status" varchar check ("status" in ('available', 'assigned', 'installed', 'lost', 'scrapped')) not null default 'available', "current_location_id" integer, "created_at" datetime, "updated_at" datetime, foreign key("material_id") references "materials"("id") on delete restrict, foreign key("current_location_id") references "stock_locations"("id") on delete set null);

CREATE TABLE "materials" ("id" integer primary key autoincrement not null, "category" varchar check ("category" in ('equipment', 'acometida', 'roseta')) not null, "type" varchar not null, "model" varchar, "is_serialized" tinyint(1) not null default '0', "is_active" tinyint(1) not null default '1', "created_at" datetime, "updated_at" datetime);

CREATE TABLE "migrations" ("id" integer primary key autoincrement not null, "migration" varchar not null, "batch" integer not null);

CREATE TABLE "password_reset_tokens" ("email" varchar not null, "token" varchar not null, "created_at" datetime, primary key ("email"));

CREATE TABLE "sessions" ("id" varchar not null, "user_id" integer, "ip_address" varchar, "user_agent" text, "payload" text not null, "last_activity" integer not null, primary key ("id"));

CREATE TABLE sqlite_sequence(name,seq);

CREATE TABLE "stock_locations" ("id" integer primary key autoincrement not null, "location_type" varchar check ("location_type" in ('warehouse', 'user')) not null, "ref_id" integer, "name" varchar not null, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "stock_movements" ("id" integer primary key autoincrement not null, "movement_type" varchar check ("movement_type" in ('transfer_in', 'transfer_out', 'consumption', 'adjustment')) not null, "material_id" integer not null, "material_serial_id" integer, "from_location_id" integer, "to_location_id" integer, "quantity" integer not null default '1', "reference_type" varchar check ("reference_type" in ('transfer', 'work_order', 'manual_adjustment')) not null, "reference_id" integer not null, "performed_at" datetime not null default CURRENT_TIMESTAMP, "performed_by" integer not null, "created_at" datetime, "updated_at" datetime, foreign key("material_id") references "materials"("id") on delete restrict, foreign key("material_serial_id") references "material_serials"("id") on delete restrict, foreign key("from_location_id") references "stock_locations"("id") on delete restrict, foreign key("to_location_id") references "stock_locations"("id") on delete restrict, foreign key("performed_by") references "users"("id") on delete restrict);

CREATE TABLE "transfer_items" ("id" integer primary key autoincrement not null, "transfer_id" integer not null, "material_id" integer not null, "quantity" integer, "material_serial_id" integer, "created_at" datetime, "updated_at" datetime, foreign key("transfer_id") references "transfers"("id") on delete restrict, foreign key("material_id") references "materials"("id") on delete restrict, foreign key("material_serial_id") references "material_serials"("id") on delete restrict);

CREATE TABLE "transfers" ("id" integer primary key autoincrement not null, "order_number" varchar not null, "from_location_id" integer not null, "to_location_id" integer not null, "initiator_user_id" integer not null, "requires_receiver_accept" tinyint(1) not null default '1', "status" varchar check ("status" in ('pending', 'accepted', 'rejected', 'cancelled')) not null default 'pending', "accepted_at" datetime, "rejected_at" datetime, "notes" text, "created_at" datetime, "updated_at" datetime, foreign key("from_location_id") references "stock_locations"("id") on delete restrict, foreign key("to_location_id") references "stock_locations"("id") on delete restrict, foreign key("initiator_user_id") references "users"("id") on delete restrict);

CREATE TABLE "users" ("id" integer primary key autoincrement not null, "name" varchar not null, "email" varchar not null, "email_verified_at" datetime, "password" varchar not null, "role" varchar check ("role" in ('admin', 'technician')) not null default 'technician', "tech_code" varchar, "remember_token" varchar, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "warehouses" ("id" integer primary key autoincrement not null, "code" varchar not null, "name" varchar not null, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "work_order_items" ("id" integer primary key autoincrement not null, "work_order_id" integer not null, "material_id" integer not null, "quantity" integer, "material_serial_id" integer, "created_at" datetime, "updated_at" datetime, foreign key("work_order_id") references "work_orders"("id") on delete restrict, foreign key("material_id") references "materials"("id") on delete restrict, foreign key("material_serial_id") references "material_serials"("id") on delete restrict);

CREATE TABLE "work_orders" ("id" integer primary key autoincrement not null, "order_number" varchar not null, "technician_id" integer not null, "technician_code" varchar not null, "technician_name" varchar not null, "status" varchar check ("status" in ('draft', 'posted', 'cancelled')) not null default 'draft', "notes" text, "created_at" datetime, "updated_at" datetime, foreign key("technician_id") references "users"("id") on delete restrict);

CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs" ("uuid");

CREATE UNIQUE INDEX "inventories_location_id_material_id_unique" on "inventories" ("location_id", "material_id");

CREATE INDEX "jobs_queue_index" on "jobs" ("queue");

CREATE UNIQUE INDEX "material_serials_serial_number_unique" on "material_serials" ("serial_number");

CREATE INDEX "material_serials_status_index" on "material_serials" ("status");

CREATE INDEX "materials_category_index" on "materials" ("category");

CREATE INDEX "materials_is_active_index" on "materials" ("is_active");

CREATE INDEX "materials_is_serialized_index" on "materials" ("is_serialized");

CREATE INDEX "sessions_last_activity_index" on "sessions" ("last_activity");

CREATE INDEX "sessions_user_id_index" on "sessions" ("user_id");

CREATE INDEX "stock_locations_location_type_index" on "stock_locations" ("location_type");

CREATE UNIQUE INDEX "stock_locations_location_type_ref_id_unique" on "stock_locations" ("location_type", "ref_id");

CREATE INDEX "stock_locations_ref_id_index" on "stock_locations" ("ref_id");

CREATE INDEX "stock_movements_movement_type_index" on "stock_movements" ("movement_type");

CREATE INDEX "stock_movements_performed_at_movement_type_index" on "stock_movements" ("performed_at", "movement_type");

CREATE INDEX "stock_movements_reference_id_index" on "stock_movements" ("reference_id");

CREATE INDEX "stock_movements_reference_type_index" on "stock_movements" ("reference_type");

CREATE UNIQUE INDEX "transfers_order_number_unique" on "transfers" ("order_number");

CREATE INDEX "transfers_status_index" on "transfers" ("status");

CREATE UNIQUE INDEX "users_email_unique" on "users" ("email");

CREATE INDEX "users_role_index" on "users" ("role");

CREATE UNIQUE INDEX "users_tech_code_unique" on "users" ("tech_code");

CREATE UNIQUE INDEX "warehouses_code_unique" on "warehouses" ("code");

CREATE UNIQUE INDEX "work_orders_order_number_unique" on "work_orders" ("order_number");

CREATE INDEX "work_orders_status_index" on "work_orders" ("status");

CREATE TRIGGER transfer_items_quantity_serial_insert BEFORE INSERT ON transfer_items FOR EACH ROW BEGIN
                SELECT CASE
                    WHEN ((NEW.quantity IS NOT NULL AND NEW.material_serial_id IS NULL) OR (NEW.quantity IS NULL AND NEW.material_serial_id IS NOT NULL))
                        THEN 0
                    ELSE RAISE(ABORT, 'quantity_or_serial_required')
                END;
            END;

CREATE TRIGGER transfer_items_quantity_serial_update BEFORE UPDATE ON transfer_items FOR EACH ROW BEGIN
                SELECT CASE
                    WHEN ((NEW.quantity IS NOT NULL AND NEW.material_serial_id IS NULL) OR (NEW.quantity IS NULL AND NEW.material_serial_id IS NOT NULL))
                        THEN 0
                    ELSE RAISE(ABORT, 'quantity_or_serial_required')
                END;
            END;

CREATE TRIGGER work_order_items_quantity_serial_insert BEFORE INSERT ON work_order_items FOR EACH ROW BEGIN
                SELECT CASE
                    WHEN ((NEW.quantity IS NOT NULL AND NEW.material_serial_id IS NULL) OR (NEW.quantity IS NULL AND NEW.material_serial_id IS NOT NULL))
                        THEN 0
                    ELSE RAISE(ABORT, 'quantity_or_serial_required')
                END;
            END;

CREATE TRIGGER work_order_items_quantity_serial_update BEFORE UPDATE ON work_order_items FOR EACH ROW BEGIN
                SELECT CASE
                    WHEN ((NEW.quantity IS NOT NULL AND NEW.material_serial_id IS NULL) OR (NEW.quantity IS NULL AND NEW.material_serial_id IS NOT NULL))
                        THEN 0
                    ELSE RAISE(ABORT, 'quantity_or_serial_required')
                END;
            END;

