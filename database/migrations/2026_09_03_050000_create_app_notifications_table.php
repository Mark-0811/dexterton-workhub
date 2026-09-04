<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('href')->nullable();
            $table->string('category')->default('info')->index();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });

        $now = now();
        $users = DB::table('users')->where('status', 'active')->get(['id', 'email']);

        foreach ($users as $user) {
            DB::table('app_notifications')->insert([
                [
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'title' => 'Project spaces are ready',
                    'body' => 'Open the sidebar Spaces list to filter projects by department.',
                    'href' => '/projects',
                    'category' => 'project',
                    'data' => json_encode(['source' => 'system']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'title' => 'Profile settings available',
                    'body' => 'You can update your profile, theme, and notification preferences.',
                    'href' => '/profile',
                    'category' => 'account',
                    'data' => json_encode(['source' => 'system']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
