<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->string('name'); $table->string('slug')->unique();
            $table->string('timezone')->default('Asia/Manila'); $table->json('branding')->nullable(); $table->timestamps();
        });
        Schema::create('offices', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name'); $table->string('code')->unique(); $table->text('address')->nullable(); $table->boolean('active')->default(true); $table->timestamps(); $table->softDeletes();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignUuid('office_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->foreignUuid('manager_id')->nullable()->after('office_id')->references('id')->on('users')->nullOnDelete();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->string('name'); $table->string('slug')->unique(); $table->boolean('protected')->default(false); $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->string('name'); $table->string('slug')->unique(); $table->timestamps();
        });
        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignUuid('role_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('user_id')->constrained()->cascadeOnDelete(); $table->primary(['role_id','user_id']);
        });
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignUuid('permission_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('role_id')->constrained()->cascadeOnDelete(); $table->primary(['permission_id','role_id']);
        });
        Schema::create('groups', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->string('code')->nullable(); $table->string('type')->default('regular'); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('group_user', function (Blueprint $table) {
            $table->foreignUuid('group_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('user_id')->constrained()->cascadeOnDelete(); $table->string('membership_role')->default('member'); $table->timestamps(); $table->primary(['group_id','user_id']);
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('code')->unique(); $table->string('name'); $table->text('description')->nullable(); $table->string('status')->default('active')->index(); $table->unsignedTinyInteger('progress')->default(0); $table->date('starts_at')->nullable(); $table->date('due_at')->nullable(); $table->json('settings')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('project_id')->constrained()->cascadeOnDelete(); $table->uuid('parent_id')->nullable(); $table->foreignUuid('assignee_id')->nullable()->constrained('users')->nullOnDelete(); $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title'); $table->text('description')->nullable(); $table->string('status')->default('todo')->index(); $table->string('priority')->default('normal'); $table->unsignedInteger('position')->default(0); $table->dateTime('starts_at')->nullable(); $table->dateTime('due_at')->nullable(); $table->dateTime('completed_at')->nullable(); $table->unsignedInteger('estimated_minutes')->nullable(); $table->unsignedInteger('spent_minutes')->default(0); $table->timestamps(); $table->softDeletes();
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('tasks')->nullOnDelete();
        });
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('depends_on_task_id')->constrained('tasks')->cascadeOnDelete(); $table->primary(['task_id','depends_on_task_id']);
        });
        Schema::create('service_flows', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete(); $table->string('name'); $table->string('slug')->unique(); $table->text('description')->nullable(); $table->json('intake_schema'); $table->json('status_pipeline'); $table->unsignedInteger('sla_minutes')->nullable(); $table->string('status')->default('draft'); $table->unsignedInteger('version')->default(1); $table->timestamp('published_at')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete(); $table->string('name'); $table->text('description')->nullable(); $table->string('status')->default('draft')->index(); $table->unsignedInteger('current_version')->default(0); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('workflow_definition_id')->constrained()->cascadeOnDelete(); $table->unsignedInteger('version'); $table->json('trigger'); $table->json('nodes'); $table->json('edges'); $table->foreignUuid('published_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('published_at')->nullable(); $table->timestamps(); $table->unique(['workflow_definition_id','version']);
        });
        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('workflow_version_id')->constrained()->restrictOnDelete(); $table->foreignUuid('started_by')->nullable()->constrained('users')->nullOnDelete(); $table->nullableUuidMorphs('subject'); $table->string('status')->default('queued')->index(); $table->json('context')->nullable(); $table->json('result')->nullable(); $table->timestamp('started_at')->nullable(); $table->timestamp('finished_at')->nullable(); $table->timestamps();
        });
        Schema::create('work_requests', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('service_flow_id')->nullable()->constrained()->nullOnDelete(); $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete(); $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->unique(); $table->string('title'); $table->text('description')->nullable(); $table->string('status')->default('draft')->index(); $table->string('priority')->default('normal'); $table->json('form_data')->nullable(); $table->unsignedInteger('current_step')->default(0); $table->timestamp('submitted_at')->nullable(); $table->timestamp('resolved_at')->nullable(); $table->timestamp('sla_due_at')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('work_request_id')->constrained()->cascadeOnDelete(); $table->unsignedInteger('sequence'); $table->string('mode')->default('sequential'); $table->foreignUuid('approver_id')->nullable()->constrained('users')->nullOnDelete(); $table->foreignUuid('delegated_to')->nullable()->constrained('users')->nullOnDelete(); $table->string('status')->default('pending')->index(); $table->text('decision_note')->nullable(); $table->timestamp('decided_at')->nullable(); $table->timestamps();
        });
        Schema::create('todos', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('user_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('assigned_by')->nullable()->constrained('users')->nullOnDelete(); $table->nullableUuidMorphs('related'); $table->string('title'); $table->text('notes')->nullable(); $table->string('priority')->default('normal'); $table->json('recurrence')->nullable(); $table->timestamp('due_at')->nullable(); $table->timestamp('completed_at')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete(); $table->uuidMorphs('commentable'); $table->text('body'); $table->json('mentions')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('attachments', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete(); $table->uuidMorphs('attachable'); $table->string('disk')->default('s3'); $table->string('path'); $table->string('original_name'); $table->string('mime_type'); $table->unsignedBigInteger('size'); $table->string('checksum',64); $table->string('scan_status')->default('pending'); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('reminders', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('user_id')->constrained()->cascadeOnDelete(); $table->nullableUuidMorphs('remindable'); $table->string('title'); $table->timestamp('remind_at'); $table->timestamp('sent_at')->nullable(); $table->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete(); $table->nullableUuidMorphs('subject'); $table->string('event')->index(); $table->json('before')->nullable(); $table->json('after')->nullable(); $table->string('ip_address',45)->nullable(); $table->text('user_agent')->nullable(); $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->string('topic')->index(); $table->string('aggregate_type'); $table->uuid('aggregate_id'); $table->json('payload'); $table->unsignedInteger('attempts')->default(0); $table->timestamp('available_at')->useCurrent(); $table->timestamp('processed_at')->nullable(); $table->timestamps();
        });
        Schema::create('migration_batches', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->string('source')->default('rework'); $table->string('status')->default('pending'); $table->json('options')->nullable(); $table->json('totals')->nullable(); $table->text('report_path')->nullable(); $table->timestamp('started_at')->nullable(); $table->timestamp('finished_at')->nullable(); $table->timestamps();
        });
        Schema::create('external_mappings', function (Blueprint $table) {
            $table->id(); $table->foreignUuid('migration_batch_id')->constrained()->cascadeOnDelete(); $table->string('entity_type'); $table->string('external_id'); $table->uuid('internal_id')->nullable(); $table->string('status')->default('mapped'); $table->json('errors')->nullable(); $table->unique(['entity_type','external_id']);
        });
    }

    public function down(): void
    {
        foreach (['external_mappings','migration_batches','outbox_events','audit_events','reminders','attachments','comments','todos','approval_steps','work_requests','workflow_runs','workflow_versions','workflow_definitions','service_flows','task_dependencies','tasks','projects','group_user','groups','permission_role','role_user','permissions','roles'] as $table) Schema::dropIfExists($table);
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropForeign(['office_id']);
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['manager_id', 'office_id', 'organization_id']);
        });
        Schema::dropIfExists('offices'); Schema::dropIfExists('organizations');
    }
};
