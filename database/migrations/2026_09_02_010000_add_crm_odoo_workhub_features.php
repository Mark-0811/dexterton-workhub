<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('app_access')->nullable()->after('preferences');
            $table->string('invitation_token')->nullable()->index()->after('remember_token');
            $table->timestamp('invited_at')->nullable()->after('invitation_token');
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('office_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_source')->nullable()->index();
            $table->string('external_id')->nullable()->index();
            $table->string('employee_code')->nullable()->index();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->string('status')->default('provisional')->index();
            $table->json('raw_payload')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['external_source', 'external_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('external_source')->nullable()->index();
            $table->string('external_id')->nullable()->index();
            $table->timestamp('source_updated_at')->nullable();
            $table->unique(['external_source', 'external_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('external_source')->nullable()->index();
            $table->string('external_id')->nullable()->index();
            $table->timestamp('source_updated_at')->nullable();
            $table->unique(['external_source', 'external_id']);
        });

        Schema::create('odoo_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Dexterton Odoo');
            $table->string('base_url')->nullable();
            $table->string('database')->nullable();
            $table->string('username')->nullable();
            $table->text('api_key')->nullable();
            $table->string('status')->default('not_configured');
            $table->json('last_result')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('odoo_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('migration_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('entity_type')->nullable();
            $table->string('external_id')->nullable();
            $table->string('status')->default('pending')->index();
            $table->uuid('internal_id')->nullable();
            $table->json('payload')->nullable();
            $table->json('errors')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_import_rows');
        Schema::dropIfExists('odoo_connections');
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['external_source', 'external_id']);
            $table->dropColumn(['external_source', 'external_id', 'source_updated_at']);
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['external_source', 'external_id']);
            $table->dropColumn(['external_source', 'external_id', 'source_updated_at']);
        });
        Schema::dropIfExists('employees');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['app_access', 'invitation_token', 'invited_at']);
        });
    }
};
