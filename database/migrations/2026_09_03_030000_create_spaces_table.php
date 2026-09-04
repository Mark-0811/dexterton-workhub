<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('department')->index();
            $table->string('color')->default('#435ebe');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignUuid('space_id')->nullable()->after('owner_id')->constrained()->nullOnDelete();
        });

        $organization = DB::table('organizations')->first();
        $owner = DB::table('users')->where('email', 'john.llavanes@dexterton.com')->first() ?? DB::table('users')->first();

        if ($organization && $owner) {
            $now = now();
            $spaces = [
                ['name' => 'Short Term Projects', 'slug' => 'softdev-short-term-projects', 'department' => 'SoftDev', 'color' => '#00bcd4', 'description' => 'Software development requests and quick project work.'],
                ['name' => 'Long Term Projects', 'slug' => 'softdev-long-term-projects', 'department' => 'SoftDev', 'color' => '#435ebe', 'description' => 'Larger program and system improvement initiatives.'],
                ['name' => 'Support & Maintenance', 'slug' => 'it-support-maintenance', 'department' => 'IT', 'color' => '#198754', 'description' => 'IT support, maintenance, and internal service tasks.'],
            ];

            foreach ($spaces as $space) {
                DB::table('spaces')->insert($space + [
                    'id' => (string) Str::uuid(),
                    'organization_id' => $organization->id,
                    'owner_id' => $owner->id,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $firstSpace = DB::table('spaces')->where('slug', 'softdev-short-term-projects')->first();
            if ($firstSpace) {
                DB::table('projects')->whereNull('space_id')->update(['space_id' => $firstSpace->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['space_id']);
            $table->dropColumn('space_id');
        });

        Schema::dropIfExists('spaces');
    }
};
