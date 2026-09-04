<?php

namespace App\Http\Controllers;

use App\Models\ApprovalStep;
use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\ServiceFlow;
use App\Models\Space;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $records = WorkRequest::with(['approvals.approver', 'project', 'space', 'serviceFlow', 'requester'])->latest()->get()
            ->map(fn (WorkRequest $record) => $this->decorateRequest($record));

        return Inertia::render('Requests/Index', [
            'requests' => $records,
            'inboxCounts' => [
                'all' => $records->count(),
                'drafts' => $records->where('status', 'draft')->where('requested_by', $request->user()->id)->count(),
                'submittedByMe' => $records->where('requested_by', $request->user()->id)->count(),
                'pendingApproval' => $records->filter(fn ($record) => in_array($record->status, ['submitted', 'in_review'], true)
                    && $record->approvals->contains(fn ($step) => $step->approver_id === $request->user()->id && $step->status === 'pending'))->count(),
                'approved' => $records->where('status', 'approved')->count(),
                'rejected' => $records->where('status', 'rejected')->count(),
            ],
            'serviceFlows' => ServiceFlow::orderBy('name')->get(),
            'spaces' => $this->requestableSpacesQuery()
                ->orderBy('department')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(Request $request, WorkRequest $requestRecord): Response
    {
        return Inertia::render('Requests/Show', [
            'requestRecord' => $this->decorateRequest($requestRecord->load(['approvals.approver', 'project', 'space', 'serviceFlow'])),
            'spaces' => $this->requestableSpacesQuery()
                ->orderBy('department')
                ->orderBy('name')
                ->get(),
            'users' => $request->user()->hasPermission('projects.manage')
                ? User::where('status', 'active')->orderBy('name')->get()
                : User::whereKey($request->user()->id)->get(),
            'canCreateProject' => $request->user()->hasPermission('projects.create'),
            'canManageProjects' => $request->user()->hasPermission('projects.manage'),
            'canApproveRequests' => $request->user()->hasPermission('requests.approve'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'service_flow_id' => ['nullable', 'exists:service_flows,id'],
            'space_id' => ['required', 'exists:spaces,id'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
        ]);

        $user = $request->user();

        $record = WorkRequest::create($data + [
            'organization_id' => $user->organization_id,
            'requested_by' => $user->id,
            'reference' => 'REQ-'.now()->format('ymd').'-'.str_pad((string) (WorkRequest::count() + 1), 4, '0', STR_PAD_LEFT),
            'status' => 'draft',
            'form_data' => ['source' => 'workhub-ui', 'space_id' => $data['space_id']],
        ]);

        ApprovalStep::create([
            'work_request_id' => $record->id,
            'sequence' => 1,
            'approver_id' => $user->manager_id ?: $user->id,
            'status' => 'pending',
        ]);

        AuditEvent::create(['actor_id' => $user->id, 'subject_type' => WorkRequest::class, 'subject_id' => $record->id, 'event' => 'request.created']);

        return redirect()->route('requests.show', $record)->with('success', 'Request drafted.');
    }

    public function transition(Request $request, WorkRequest $requestRecord, string $action): RedirectResponse
    {
        $allowed = [
            'submit' => ['from' => ['draft'], 'to' => 'submitted'],
            'approve' => ['from' => ['submitted', 'in_review'], 'to' => 'approved'],
            'reject' => ['from' => ['submitted', 'in_review'], 'to' => 'rejected'],
            'cancel' => ['from' => ['draft', 'submitted', 'in_review'], 'to' => 'cancelled'],
        ];

        abort_unless(isset($allowed[$action]) && in_array($requestRecord->status, $allowed[$action]['from'], true), 422);
        abort_unless($this->canTransition($request, $requestRecord, $action), 403);

        $before = $requestRecord->only(['status', 'current_step']);
        $nextStep = match ($action) {
            'submit' => 1,
            'approve', 'reject', 'cancel' => 99,
            default => $requestRecord->current_step,
        };

        $requestRecord->forceFill([
            'status' => $allowed[$action]['to'],
            'current_step' => $nextStep,
            'submitted_at' => $action === 'submit' ? now() : $requestRecord->submitted_at,
            'resolved_at' => in_array($action, ['approve', 'reject', 'cancel'], true) ? now() : null,
        ])->save();

        AuditEvent::create([
            'actor_id' => $request->user()->id,
            'subject_type' => WorkRequest::class,
            'subject_id' => $requestRecord->id,
            'event' => "request.$action",
            'before' => $before,
            'after' => $requestRecord->only(['status', 'current_step']),
        ]);

        return back()->with('success', 'Request updated.');
    }

    public function createProject(Request $request, WorkRequest $requestRecord): RedirectResponse
    {
        if ($requestRecord->project_id) {
            return redirect()->route('projects.show', $requestRecord->project_id)
                ->with('success', 'This request is already linked to a project.');
        }

        if ($requestRecord->status !== 'approved') {
            return back()->with('error', 'Approve the request first before creating a project from it.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'space_id' => ['nullable', 'exists:spaces,id'],
            'due_at' => ['nullable', 'date'],
        ]);

        if (($data['space_id'] ?? null) && ! in_array($data['space_id'], $request->user()->visibleSpaceIds(), true)) {
            return back()->withErrors(['space_id' => 'You do not have access to create projects in this Space.'])->withInput();
        }

        $ownerId = $request->user()->hasPermission('projects.manage')
            ? ($data['owner_id'] ?: $requestRecord->requested_by)
            : $request->user()->id;

        $project = Project::create([
            'organization_id' => $requestRecord->organization_id,
            'owner_id' => $ownerId,
            'space_id' => $data['space_id'] ?? $requestRecord->space_id,
            'code' => $this->nextProjectCode(),
            'name' => $data['name'],
            'description' => $data['description'] ?? $requestRecord->description,
            'status' => 'active',
            'progress' => 0,
            'due_at' => $data['due_at'] ?? $requestRecord->sla_due_at,
            'settings' => [
                'source' => 'work_request',
                'request_id' => $requestRecord->id,
                'request_reference' => $requestRecord->reference,
            ],
        ]);

        $before = $requestRecord->only(['project_id']);
        $requestRecord->forceFill(['project_id' => $project->id])->save();

        AuditEvent::create([
            'actor_id' => $request->user()->id,
            'subject_type' => WorkRequest::class,
            'subject_id' => $requestRecord->id,
            'event' => 'request.project_created',
            'before' => $before,
            'after' => ['project_id' => $project->id, 'project_code' => $project->code, 'project_name' => $project->name, 'space_id' => $project->space_id],
        ]);

        AuditEvent::create([
            'actor_id' => $request->user()->id,
            'subject_type' => Project::class,
            'subject_id' => $project->id,
            'event' => 'project.created',
            'after' => $project->only(['code', 'name', 'owner_id', 'space_id', 'status']),
        ]);

        return redirect()->route('projects.show', $project)->with('success', 'Project created from request '.$requestRecord->reference.'.');
    }

    public function createTask(Request $request, WorkRequest $requestRecord): RedirectResponse
    {
        if ($requestRecord->status !== 'approved') {
            return back()->with('error', 'Approve the request first before assigning task work.');
        }

        if (! $requestRecord->project_id) {
            return back()->with('error', 'Create a linked project first, then assign tasks from this request.');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'status' => ['required', 'in:todo,doing,review,done,cancelled'],
            'due_at' => ['nullable', 'date'],
        ]);

        $task = Task::create($data + [
            'project_id' => $requestRecord->project_id,
            'created_by' => $request->user()->id,
        ]);

        AuditEvent::create([
            'actor_id' => $request->user()->id,
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'event' => 'request.task_created',
            'after' => ['request_reference' => $requestRecord->reference] + $task->only(['title', 'assignee_id', 'priority', 'status', 'due_at']),
        ]);

        return redirect()->route('projects.show', $requestRecord->project_id)->with('success', 'Task created from request '.$requestRecord->reference.'.');
    }

    private function nextProjectCode(): string
    {
        $next = Project::withTrashed()->count() + 1;

        do {
            $code = 'PRJ-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $next++;
        } while (Project::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    private function requestableSpacesQuery()
    {
        return Space::where('active', true);
    }

    private function canTransition(Request $request, WorkRequest $requestRecord, string $action): bool
    {
        $user = $request->user();

        if (in_array($action, ['approve', 'reject'], true)) {
            return $user->hasPermission('requests.approve');
        }

        if ($user->hasPermission('requests.approve')) {
            return true;
        }

        return $user->hasPermission('requests.create') && $requestRecord->requested_by === $user->id;
    }

    private function decorateRequest(WorkRequest $record): WorkRequest
    {
        $record->setAttribute('flow_stage', $this->flowStage($record));
        $record->setAttribute('stage_steps', $this->stageSteps($record));

        return $record;
    }

    private function flowStage(WorkRequest $record): array
    {
        $labels = [
            'draft' => ['label' => 'Draft', 'hint' => 'Requester is still preparing it.', 'color' => 'secondary'],
            'submitted' => ['label' => 'Submitted', 'hint' => 'Waiting for review.', 'color' => 'primary'],
            'in_review' => ['label' => 'In review', 'hint' => 'Approver is checking the request.', 'color' => 'warning'],
            'approved' => ['label' => $record->project_id ? 'Project created' : 'Approved', 'hint' => $record->project_id ? 'Work is now tracked as a project.' : 'Ready to become a project.', 'color' => 'success'],
            'rejected' => ['label' => 'Rejected', 'hint' => 'Request was declined.', 'color' => 'danger'],
            'cancelled' => ['label' => 'Cancelled', 'hint' => 'Request was cancelled.', 'color' => 'danger'],
        ];

        return $labels[$record->status] ?? ['label' => str($record->status)->replace('_', ' ')->title()->toString(), 'hint' => 'Current request stage.', 'color' => 'primary'];
    }

    private function stageSteps(WorkRequest $record): array
    {
        $order = ['draft', 'submitted', 'in_review', 'approved'];
        $current = $record->project_id ? 4 : array_search($record->status, $order, true);
        $current = $current === false ? 0 : $current;

        return [
            ['key' => 'draft', 'label' => 'Draft', 'done' => $current >= 0],
            ['key' => 'submitted', 'label' => 'Submitted', 'done' => $current >= 1],
            ['key' => 'in_review', 'label' => 'Review', 'done' => $current >= 2 || in_array($record->status, ['approved', 'rejected'], true)],
            ['key' => 'approved', 'label' => 'Approved', 'done' => $record->status === 'approved' || $record->project_id],
            ['key' => 'project', 'label' => 'Project', 'done' => (bool) $record->project_id],
        ];
    }
}
