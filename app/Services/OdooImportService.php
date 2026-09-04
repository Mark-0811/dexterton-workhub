<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\MigrationBatch;
use App\Models\OdooImportRow;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OdooImportService
{
    public function importCsv(UploadedFile $file, User $actor): MigrationBatch
    {
        $batch = MigrationBatch::create([
            'source' => 'odoo_csv',
            'status' => 'running',
            'options' => ['filename' => $file->getClientOriginalName()],
            'started_at' => now(),
        ]);

        $handle = fopen($file->getRealPath(), 'r');
        $headers = array_map(fn ($h) => Str::of((string) $h)->lower()->trim()->replace(' ', '_')->toString(), fgetcsv($handle) ?: []);
        $totals = ['processed' => 0, 'imported' => 0, 'failed' => 0];

        while (($values = fgetcsv($handle)) !== false) {
            $totals['processed']++;
            $normalizedValues = array_slice(array_pad($values, count($headers), null), 0, count($headers));
            $payload = array_combine($headers, $normalizedValues) ?: [];
            $entity = strtolower($payload['type'] ?? $payload['entity_type'] ?? 'employee');
            $row = OdooImportRow::create([
                'migration_batch_id' => $batch->id,
                'row_number' => $totals['processed'] + 1,
                'entity_type' => $entity,
                'external_id' => $payload['external_id'] ?? $payload['id'] ?? null,
                'payload' => $payload,
            ]);

            try {
                $record = DB::transaction(fn () => $this->upsert($entity, $payload, $actor));
                $row->update(['status' => 'imported', 'internal_id' => $record?->id]);
                $totals['imported']++;
            } catch (\Throwable $exception) {
                $row->update(['status' => 'failed', 'errors' => ['message' => $exception->getMessage()]]);
                $totals['failed']++;
            }
        }

        fclose($handle);
        $batch->update(['status' => $totals['failed'] ? 'completed_with_errors' : 'completed', 'totals' => $totals, 'finished_at' => now()]);

        return $batch;
    }

    public function upsert(string $entity, array $payload, User $actor)
    {
        return match ($entity) {
            'project' => $this->upsertProject($payload, $actor),
            'task', 'work', 'assignment' => $this->upsertTask($payload, $actor),
            default => $this->upsertEmployee($payload, $actor),
        };
    }

    public function upsertEmployee(array $payload, User $actor): Employee
    {
        $organizationId = $actor->organization_id ?? Organization::first()?->id;
        $email = $payload['email'] ?? $payload['work_email'] ?? null;
        $user = $email ? User::where('email', $email)->first() : null;
        $manager = ! empty($payload['manager_email']) ? User::where('email', $payload['manager_email'])->first() : null;

        return Employee::updateOrCreate([
            'external_source' => 'odoo',
            'external_id' => (string) ($payload['external_id'] ?? $payload['id'] ?? $email ?? Str::uuid()),
        ], [
            'organization_id' => $organizationId,
            'user_id' => $user?->id,
            'manager_id' => $manager?->id,
            'name' => $payload['name'] ?? $payload['employee_name'] ?? $email ?? 'Unnamed employee',
            'email' => $email,
            'employee_code' => $payload['employee_code'] ?? $payload['code'] ?? null,
            'job_title' => $payload['job_title'] ?? $payload['title'] ?? null,
            'department' => $payload['department'] ?? null,
            'status' => $user ? 'linked' : ($payload['active'] ?? true ? 'provisional' : 'inactive'),
            'raw_payload' => $payload,
            'source_updated_at' => now(),
        ]);
    }

    public function upsertProject(array $payload, User $actor): Project
    {
        $owner = $this->resolveUser($payload['owner_email'] ?? $payload['manager_email'] ?? null) ?? $actor;
        $externalId = (string) ($payload['external_id'] ?? $payload['id'] ?? $payload['code'] ?? Str::slug($payload['name'] ?? Str::uuid()));

        return Project::updateOrCreate([
            'external_source' => 'odoo',
            'external_id' => $externalId,
        ], [
            'organization_id' => $actor->organization_id,
            'owner_id' => $owner->id,
            'code' => $payload['code'] ?? 'ODOO-'.Str::upper(Str::limit(Str::slug($externalId, ''), 18, '')),
            'name' => $payload['name'] ?? $payload['project_name'] ?? 'Odoo Project '.$externalId,
            'description' => $payload['description'] ?? null,
            'status' => $payload['status'] ?? 'active',
            'progress' => (int) ($payload['progress'] ?? 0),
            'due_at' => $payload['due_at'] ?? $payload['date_deadline'] ?? null,
            'settings' => ['source' => 'odoo'],
            'source_updated_at' => now(),
        ]);
    }

    public function upsertTask(array $payload, User $actor): Task
    {
        $project = $this->projectForTask($payload, $actor);
        $assignee = $this->resolveUser($payload['assignee_email'] ?? $payload['employee_email'] ?? null);
        $externalId = (string) ($payload['external_id'] ?? $payload['id'] ?? Str::uuid());

        return Task::updateOrCreate([
            'external_source' => 'odoo',
            'external_id' => $externalId,
        ], [
            'project_id' => $project->id,
            'assignee_id' => $assignee?->id,
            'created_by' => $actor->id,
            'title' => $payload['title'] ?? $payload['name'] ?? 'Odoo Task '.$externalId,
            'description' => $payload['description'] ?? null,
            'status' => $this->normalizeStatus($payload['status'] ?? null),
            'priority' => $this->normalizePriority($payload['priority'] ?? null),
            'due_at' => $payload['due_at'] ?? $payload['date_deadline'] ?? null,
            'source_updated_at' => now(),
        ]);
    }

    private function resolveUser(?string $email): ?User
    {
        return $email ? User::where('email', $email)->first() : null;
    }

    private function projectForTask(array $payload, User $actor): Project
    {
        $externalProjectId = $payload['project_external_id'] ?? $payload['project_id'] ?? null;
        if ($externalProjectId) {
            $project = Project::where('external_source', 'odoo')->where('external_id', (string) $externalProjectId)->first();
            if ($project) {
                return $project;
            }
        }

        return Project::firstOrCreate([
            'code' => $payload['project_code'] ?? 'ODOO-INBOX',
        ], [
            'organization_id' => $actor->organization_id,
            'owner_id' => $actor->id,
            'name' => $payload['project_name'] ?? 'Odoo Imported Work',
            'status' => 'active',
        ]);
    }

    private function normalizeStatus(?string $status): string
    {
        $value = Str::of((string) $status)->lower()->trim()->replace([' ', '-'], '_')->toString();

        if (in_array($value, ['done', 'completed', 'complete', 'closed'], true)) {
            return 'done';
        }

        if (in_array($value, ['cancelled', 'canceled', 'cancel'], true)) {
            return 'cancelled';
        }

        if (str_contains($value, 'review') || str_contains($value, 'approval') || str_contains($value, 'test')) {
            return 'review';
        }

        if (in_array($value, ['doing', 'in_progress', 'progress', 'working', 'ongoing', 'started'], true)) {
            return 'doing';
        }

        return 'todo';
    }

    private function normalizePriority(?string $priority): string
    {
        $value = Str::of((string) $priority)->lower()->trim()->toString();

        return in_array($value, ['low', 'normal', 'high', 'urgent'], true) ? $value : 'normal';
    }
}
