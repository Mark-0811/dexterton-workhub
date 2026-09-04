<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\DatasetReport;
use App\Models\MigrationBatch;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DatasetReportController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Datasets/Index', [
            'reports' => DatasetReport::with('creator')->latest()->get(),
            'sources' => $this->sources(),
            'stats' => [
                'projects' => Project::count(),
                'tasks' => Task::count(),
                'requests' => WorkRequest::count(),
                'employees' => User::where('account_type', '!=', 'guest')->count(),
                'imports' => MigrationBatch::whereIn('source', ['odoo_csv', 'odoo_api'])->count(),
                'reports' => DatasetReport::count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sourceKeys = array_keys($this->sources());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'source' => ['required', 'in:'.implode(',', $sourceKeys)],
            'description' => ['nullable', 'string'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string', 'max:80'],
            'filter_notes' => ['nullable', 'string'],
            'visibility' => ['required', 'in:admins,managers,everyone'],
        ]);

        $allowedColumns = $this->sources()[$data['source']]['columns'];
        $columns = array_values(array_intersect($data['columns'], $allowedColumns));
        if (! count($columns)) {
            return back()->withErrors(['columns' => 'Choose at least one valid report column.'])->withInput();
        }

        $report = DatasetReport::create([
            'organization_id' => $request->user()->organization_id ?? Organization::first()?->id,
            'created_by' => $request->user()->id,
            'name' => $data['name'],
            'source' => $data['source'],
            'description' => $data['description'] ?? null,
            'columns' => $columns,
            'filters' => ['notes' => $data['filter_notes'] ?? null],
            'visibility' => $data['visibility'],
            'status' => 'active',
        ]);

        AuditEvent::create([
            'actor_id' => $request->user()->id,
            'subject_type' => DatasetReport::class,
            'subject_id' => $report->id,
            'event' => 'dataset.report_created',
            'after' => $report->only(['name', 'source', 'columns', 'visibility']),
        ]);

        return back()->with('success', 'Report created.');
    }

    private function sources(): array
    {
        return [
            'projects' => [
                'label' => 'Projects',
                'description' => 'Project owner, Space, progress, due dates, and status.',
                'columns' => ['code', 'name', 'owner', 'space', 'status', 'progress', 'due_at', 'tasks_count'],
            ],
            'tasks' => [
                'label' => 'Tasks',
                'description' => 'Work assignments, assignees, priorities, stages, and deadlines.',
                'columns' => ['title', 'project', 'assignee', 'status', 'priority', 'due_at', 'completed_at'],
            ],
            'requests' => [
                'label' => 'Requests',
                'description' => 'Request references, approval status, priority, SLA, and linked projects.',
                'columns' => ['reference', 'title', 'status', 'priority', 'requested_by', 'submitted_at', 'resolved_at', 'project'],
            ],
            'employees' => [
                'label' => 'Employees',
                'description' => 'Employee directory, department, office, manager, and account status.',
                'columns' => ['name', 'email', 'job_title', 'department', 'manager', 'office', 'status', 'source'],
            ],
            'odoo_imports' => [
                'label' => 'Odoo imports',
                'description' => 'CSV/API import batches, totals, failures, and sync status.',
                'columns' => ['source', 'status', 'processed', 'imported', 'failed', 'started_at', 'finished_at'],
            ],
            'audit' => [
                'label' => 'Audit trail',
                'description' => 'Readable privileged and business activity history.',
                'columns' => ['actor', 'event', 'subject', 'category', 'created_at', 'severity'],
            ],
        ];
    }
}
