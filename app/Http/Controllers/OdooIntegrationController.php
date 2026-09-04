<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\MigrationBatch;
use App\Models\OdooImportRow;
use App\Models\OdooConnection;
use App\Models\Organization;
use App\Services\OdooImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class OdooIntegrationController extends Controller
{
    private array $fieldCache = [];
    private array $userEmailCache = [];

    public function index(Request $request): Response
    {
        $connection = $this->connectionFor($request);

        return Inertia::render('Integrations/Odoo', [
            'connection' => $connection->makeHidden('api_key'),
            'batches' => MigrationBatch::whereIn('source', ['odoo_csv', 'odoo_api'])->latest()->limit(10)->get(),
            'csvTemplate' => 'type,external_id,name,email,job_title,department,manager_email,project_external_id,project_name,title,assignee_email,due_at,status,priority',
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'base_url' => ['nullable', 'url'],
            'database' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string'],
        ]);

        $connection = $this->connectionFor($request);
        if (empty($data['api_key'])) {
            unset($data['api_key']);
        }
        $connection->update($data + ['status' => 'configured']);

        return back()->with('success', 'Odoo settings saved.');
    }

    public function test(Request $request): RedirectResponse
    {
        $connection = $this->connectionFor($request);

        try {
            $uid = $this->authenticate($connection);
            $connection->update(['status' => 'connected', 'last_tested_at' => now(), 'last_result' => ['message' => "Connected to Odoo as UID $uid"]]);
            AuditEvent::create(['actor_id' => $request->user()->id, 'event' => 'odoo.api_tested', 'after' => ['status' => 'connected']]);

            return back()->with('success', "Odoo connection successful. UID $uid.");
        } catch (\Throwable $exception) {
            $connection->update(['status' => 'failed', 'last_tested_at' => now(), 'last_result' => ['message' => $exception->getMessage()]]);
            return back()->with('error', 'Odoo connection failed: '.$exception->getMessage());
        }
    }

    public function importCsv(Request $request, OdooImportService $importer): RedirectResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        $batch = $importer->importCsv($data['file'], $request->user());
        AuditEvent::create(['actor_id' => $request->user()->id, 'subject_type' => MigrationBatch::class, 'subject_id' => $batch->id, 'event' => 'odoo.csv_import', 'after' => $batch->totals]);

        return redirect()->route('odoo.imports.show', $batch)->with('success', 'Odoo CSV import completed.');
    }

    public function showImport(MigrationBatch $batch): Response
    {
        return Inertia::render('Integrations/OdooImportShow', [
            'batch' => $batch->load('rows'),
        ]);
    }

    public function sync(Request $request, OdooImportService $importer): RedirectResponse
    {
        $connection = $this->connectionFor($request);

        $batch = null;

        try {
            $this->validateConfigured($connection);
            $uid = $this->authenticate($connection);
            $batch = MigrationBatch::create(['source' => 'odoo_api', 'status' => 'running', 'started_at' => now(), 'options' => ['uid' => $uid]]);
            $totals = ['processed' => 0, 'imported' => 0, 'failed' => 0];

            foreach ($this->searchRead($connection, $uid, 'hr.employee', ['id', 'name', 'work_email', 'job_title', 'department_id', 'parent_id']) as $row) {
                $payload = [
                    'type' => 'employee',
                    'external_id' => $row['id'],
                    'name' => $row['name'] ?? null,
                    'email' => $row['work_email'] ?? null,
                    'job_title' => $row['job_title'] ?? null,
                    'department' => is_array($row['department_id'] ?? null) ? $row['department_id'][1] : null,
                ];

                $this->upsertApiRow($batch, $totals, $importer, 'employee', $payload, $request);
            }

            foreach ($this->searchRead($connection, $uid, 'project.project', ['id', 'name', 'date', 'user_id']) as $row) {
                $ownerOdooId = is_array($row['user_id'] ?? null) ? $row['user_id'][0] : ($row['user_id'] ?? null);
                $payload = [
                    'type' => 'project',
                    'external_id' => $row['id'],
                    'name' => $row['name'] ?? null,
                    'due_at' => $row['date'] ?? null,
                    'owner_email' => $ownerOdooId ? $this->odooUserEmail($connection, $uid, (int) $ownerOdooId) : null,
                ];

                $this->upsertApiRow($batch, $totals, $importer, 'project', $payload, $request);
            }

            foreach ($this->searchRead($connection, $uid, 'project.task', ['id', 'name', 'project_id', 'user_id', 'user_ids', 'date_deadline', 'stage_id']) as $row) {
                $assigneeOdooId = null;
                if (is_array($row['user_id'] ?? null)) {
                    $assigneeOdooId = $row['user_id'][0] ?? null;
                } elseif (is_array($row['user_ids'] ?? null) && count($row['user_ids'])) {
                    $assigneeOdooId = $row['user_ids'][0];
                }

                $payload = [
                    'type' => 'task',
                    'external_id' => $row['id'],
                    'name' => $row['name'] ?? null,
                    'project_external_id' => is_array($row['project_id'] ?? null) ? $row['project_id'][0] : null,
                    'due_at' => $row['date_deadline'] ?? null,
                    'status' => is_array($row['stage_id'] ?? null) ? $row['stage_id'][1] : 'todo',
                    'assignee_email' => $assigneeOdooId ? $this->odooUserEmail($connection, $uid, (int) $assigneeOdooId) : null,
                ];

                $this->upsertApiRow($batch, $totals, $importer, 'task', $payload, $request);
            }

            $batch->update(['status' => $totals['failed'] ? 'completed_with_errors' : 'completed', 'totals' => $totals, 'finished_at' => now()]);
            $connection->update(['last_synced_at' => now(), 'last_result' => $totals]);
            AuditEvent::create(['actor_id' => $request->user()->id, 'subject_type' => MigrationBatch::class, 'subject_id' => $batch->id, 'event' => 'odoo.api_sync', 'after' => $totals]);

            return redirect()->route('odoo.imports.show', $batch)->with($totals['failed'] ? 'error' : 'success', $totals['failed'] ? 'Odoo sync completed with row errors. Open the report to review.' : 'Odoo API sync completed.');
        } catch (\Throwable $exception) {
            if ($batch) {
                $batch->update(['status' => 'failed', 'totals' => ($totals ?? []) + ['error' => $exception->getMessage()], 'finished_at' => now()]);
            }
            $connection->update(['status' => 'failed', 'last_result' => ['message' => $exception->getMessage()]]);

            return back()->with('error', 'Odoo sync failed: '.$exception->getMessage());
        }
    }

    private function validateConfigured(OdooConnection $connection): void
    {
        if (! $connection->base_url || ! $connection->database || ! $connection->username || ! $connection->api_key) {
            throw new \RuntimeException('Please complete the Odoo base URL, database, username, and API key before syncing.');
        }
    }

    private function connectionFor(Request $request): OdooConnection
    {
        return OdooConnection::firstOrCreate([
            'organization_id' => $request->user()->organization_id ?? Organization::first()?->id,
        ]);
    }

    private function authenticate(OdooConnection $connection): int
    {
        $response = $this->odooCall($connection, [
            'service' => 'common',
            'method' => 'login',
            'args' => [$connection->database, $connection->username, $connection->api_key],
        ], 15);

        $uid = $response['result'] ?? null;
        if (! $uid) {
            throw new \RuntimeException('Odoo did not return a user id.');
        }

        return (int) $uid;
    }

    private function searchRead(OdooConnection $connection, int $uid, string $model, array $fields): array
    {
        $readableFields = $this->readableFields($connection, $uid, $model, $fields);
        if (! in_array('id', $readableFields, true)) {
            array_unshift($readableFields, 'id');
        }

        $response = $this->odooCall($connection, [
            'service' => 'object',
            'method' => 'execute_kw',
            'args' => [$connection->database, $uid, $connection->api_key, $model, 'search_read', [[]], ['fields' => $readableFields, 'limit' => 500]],
        ], 30);

        return $response['result'] ?? [];
    }

    private function readableFields(OdooConnection $connection, int $uid, string $model, array $wanted): array
    {
        $available = $this->availableFields($connection, $uid, $model);
        if (! $available) {
            return $wanted;
        }

        return array_values(array_intersect($wanted, $available));
    }

    private function availableFields(OdooConnection $connection, int $uid, string $model): array
    {
        if (array_key_exists($model, $this->fieldCache)) {
            return $this->fieldCache[$model];
        }

        try {
            $response = $this->odooCall($connection, [
                'service' => 'object',
                'method' => 'execute_kw',
                'args' => [$connection->database, $uid, $connection->api_key, $model, 'fields_get', [], ['attributes' => ['string']]],
            ], 30);

            return $this->fieldCache[$model] = array_keys($response['result'] ?? []);
        } catch (\Throwable) {
            return $this->fieldCache[$model] = [];
        }
    }

    private function odooUserEmail(OdooConnection $connection, int $uid, int $odooUserId): ?string
    {
        if (array_key_exists($odooUserId, $this->userEmailCache)) {
            return $this->userEmailCache[$odooUserId];
        }

        $fields = $this->readableFields($connection, $uid, 'res.users', ['id', 'login', 'email']);
        $response = $this->odooCall($connection, [
            'service' => 'object',
            'method' => 'execute_kw',
            'args' => [$connection->database, $uid, $connection->api_key, 'res.users', 'search_read', [[['id', '=', $odooUserId]]], ['fields' => $fields ?: ['id', 'login'], 'limit' => 1]],
        ], 30);
        $row = $response['result'][0] ?? [];

        return $this->userEmailCache[$odooUserId] = $row['email'] ?? $row['login'] ?? null;
    }

    private function upsertApiRow(MigrationBatch $batch, array &$totals, OdooImportService $importer, string $entity, array $payload, Request $request): void
    {
        $totals['processed']++;
        $row = OdooImportRow::create([
            'migration_batch_id' => $batch->id,
            'row_number' => $totals['processed'],
            'entity_type' => $entity,
            'external_id' => $payload['external_id'] ?? null,
            'payload' => $payload,
        ]);

        try {
            $record = $importer->upsert($entity, $payload, $request->user());
            $row->update(['status' => 'imported', 'internal_id' => $record?->id]);
            $totals['imported']++;
        } catch (\Throwable $exception) {
            $row->update(['status' => 'failed', 'errors' => ['message' => $exception->getMessage()]]);
            $totals['failed']++;
        }
    }

    private function odooCall(OdooConnection $connection, array $params, int $timeout): array
    {
        $response = Http::timeout($timeout)->post(rtrim((string) $connection->base_url, '/').'/jsonrpc', [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => $params,
        ])->throw()->json();

        if (isset($response['error'])) {
            $message = $response['error']['data']['message']
                ?? $response['error']['message']
                ?? 'Odoo returned an API error.';

            throw new \RuntimeException($message);
        }

        return $response;
    }
}
