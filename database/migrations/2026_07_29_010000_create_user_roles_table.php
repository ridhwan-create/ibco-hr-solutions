<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32);
            $table->timestamps();

            $table->unique(['user_id', 'role']);
            $table->index('role');
        });

        DB::table('users')
            ->select(['id', 'role'])
            ->orderBy('id')
            ->chunkById(100, function ($users) {
                $now = now();
                $rows = $users->map(fn ($user) => [
                    'user_id' => $user->id,
                    'role' => $user->role ?: 'viewer',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows !== []) {
                    DB::table('user_roles')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
