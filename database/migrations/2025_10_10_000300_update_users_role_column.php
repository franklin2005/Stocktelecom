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
        Schema::dropIfExists('users_tmp');

        Schema::create('users_tmp', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 50)->default('technician');
            $table->string('tech_code')->unique()->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('role');
        });

        $users = DB::table('users')->get();

        foreach ($users as $user) {
            DB::table('users_tmp')->insert([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'password' => $user->password,
                'role' => $user->role,
                'tech_code' => $user->tech_code,
                'remember_token' => $user->remember_token,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]);
        }

        DB::statement('PRAGMA foreign_keys = OFF');
        Schema::drop('users');
        DB::statement('PRAGMA foreign_keys = ON');

        Schema::rename('users_tmp', 'users');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_tmp');

        Schema::create('users_tmp', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'technician'])->default('technician');
            $table->string('tech_code')->unique()->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('role');
        });

        $users = DB::table('users')->get();

        foreach ($users as $user) {
            $role = in_array($user->role, ['admin', 'technician'], true) ? $user->role : 'technician';

            DB::table('users_tmp')->insert([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'password' => $user->password,
                'role' => $role,
                'tech_code' => $user->tech_code,
                'remember_token' => $user->remember_token,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]);
        }

        DB::statement('PRAGMA foreign_keys = OFF');
        Schema::drop('users');
        DB::statement('PRAGMA foreign_keys = ON');

        Schema::rename('users_tmp', 'users');
    }
};
