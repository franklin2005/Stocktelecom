<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role_tmp', 50)->default('technician');
            $table->string('tech_code_tmp', 100)->nullable();
        });

        DB::table('users')->select('id', 'role', 'tech_code')->orderBy('id')->chunk(100, function ($users) {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update([
                    'role_tmp' => $user->role ?? 'technician',
                    'tech_code_tmp' => $user->tech_code,
                ]);
            }
        });

        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['role']);
            });

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }

        if (Schema::hasColumn('users', 'tech_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['tech_code']);
            });

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('tech_code');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('role_tmp', 'role');
            $table->renameColumn('tech_code_tmp', 'tech_code');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
            $table->unique('tech_code');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role_tmp', ['admin', 'technician'])->default('technician');
            $table->string('tech_code_tmp', 100)->nullable();
        });

        DB::table('users')->select('id', 'role', 'tech_code')->orderBy('id')->chunk(100, function ($users) {
            foreach ($users as $user) {
                $role = in_array($user->role, ['admin', 'technician'], true) ? $user->role : 'technician';

                DB::table('users')->where('id', $user->id)->update([
                    'role_tmp' => $role,
                    'tech_code_tmp' => $user->tech_code,
                ]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropUnique(['tech_code']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'tech_code']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('role_tmp', 'role');
            $table->renameColumn('tech_code_tmp', 'tech_code');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
            $table->unique('tech_code');
        });
    }
};
