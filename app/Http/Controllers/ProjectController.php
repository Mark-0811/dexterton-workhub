<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\AppSetting;
use App\Models\Project;
use App\Models\Space;
use App\Models\Task;
use App\Models\User;
use App\Http\Controllers\MailSettingsController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $canManageProjects = $request->user()->hasPermission('projects.manage');
        $activeSpaceId = $request->query('space');
        $visibleSpaceIds = $request->user()->visibleSpaceIds();

        if ($activeSpaceId && ! in_array($activeSpaceId, $visibleSpaceIds, true)) {
            abort(403);
        }

        $projects = $this->visibleProjectsQuery($request)
            ->with(['owner', 'space'])
            ->withCount('tasks')
            ->when($activeSpaceId, fn ($query) => $query->where('space_id', $activeSpaceId))
            ->latest()
            ->get();

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'users' => $this->assignableUsers($request),
            'spaces' => $this->visibleSpacesQuery($request)->get(),
            'nextProjectCode' => $this->nextProjectCode(),
            'canCreateProjects' => $request->user()->hasPermission('projects.create'),
            'canManageProjects' => $canManageProjects,
            'activeSpaceId' => $activeSpaceId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:50', 'unique:projects,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'space_id' => ['nullable', 'exists:spaces,id'],
            'status' => ['required', 'in:active,on_hold,completed,cancelled'],
            'due_at' => ['nullable', 'date'],
        ]);

        if (($data['space_id'] ?? null) && ! in_array($data['space_id'], $request->user()->visibleSpaceIds(), true)) {
            return back()->withErrors(['space_id' => 'You do not have access to create projects in this Space.'])->withInput();
        }

        $data['code'] = $data['code'] ?: $this->nextProjectCode();
        $data['owner_id'] = $request->user()->hasPermission('projects.manage')
            ? ($data['owner_id'] ?: $request->user()->id)
            : $request->user()->id;

        $project = Project::create($data + ['organization_id' => $request->user()->organization_id, 'progress' => 0]);
        AuditEvent::create(['actor_id' => $request->user()->id, 'subject_type' => Project::class, 'subject_id' => $project->id, 'event' => 'project.created', 'after' => $project->only(['code', 'name', 'owner_id', 'status'])]);

        return redirect()->route('projects.show', $project)->with('success', 'Project created.');
    }

    public function show(Request $request, Project $project): Response
    {
        abort_unless($this->userCanSeeProject($request, $project), 403);

        return Inertia::render('Projects/Show', [
            'project' => $project->load(['owner', 'space', 'tasks.assignee']),
            'users' => $this->assignableUsers($request),
            'spaces' => $this->visibleSpacesQuery($request)->get(),
            'taskStatusColors' => AppSetting::getValue('task.board_colors', MailSettingsController::defaultBoardColors()),
            'canManageProjects' => $request->user()->hasPermission('projects.manage'),
            'relatedRequests' => \App\Models\WorkRequest::with(['requester', 'serviceFlow'])
                ->where('project_id', $project->id)
                ->latest()
                ->get(),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $before = $project->only(['name', 'description', 'owner_id', 'space_id', 'status', 'progress', 'due_at']);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'owner_id' => ['required', 'exists:users,id'],
            'space_id' => ['nullable', 'exists:spaces,id'],
            'status' => ['required', 'in:active,on_hold,completed,cancelled'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'due_at' => ['nullable', 'date'],
        ]);

        $project->update($data);
        AuditEvent::create(['actor_id' => $request->user()->id, 'subject_type' => Project::class, 'subject_id' => $project->id, 'event' => 'project.updated', 'before' => $before, 'after' => $project->only(['name', 'description', 'owner_id', 'space_id', 'status', 'progress', 'due_at'])]);

        return back()->with('success', 'Project assignment updated.');
    }

    public function storeTask(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'status' => ['required', 'in:todo,doing,review,done,cancelled'],
            'due_at' => ['nullable', 'date'],
        ]);

        $task = Task::create($data + ['project_id' => $project->id, 'created_by' => $request->user()->id]);
        AuditEvent::create(['actor_id' => $request->user()->id, 'subject_type' => Task::class, 'subject_id' => $task->id, 'event' => 'task.created', 'after' => $task->only(['title', 'assignee_id', 'priority', 'status', 'due_at'])]);

        return back()->with('success', 'Task assigned.');
    }

    public function updateTask(Request $request, Project $project, Task $task): RedirectResponse
    {
        abort_unless($task->project_id === $project->id, 404);
        $before = $task->only(['assignee_id', 'priority', 'status', 'due_at']);
        $data = $request->validate([
            'assignee_id' => ['nullable', 'exists:users,id'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'status' => ['required', 'in:todo,doing,review,done,cancelled'],
            'due_at' => ['nullable', 'date'],
        ]);

        $task->update($data + ['completed_at' => $data['status'] === 'done' ? now() : null]);
        AuditEvent::create(['actor_id' => $request->user()->id, 'subject_type' => Task::class, 'subject_id' => $task->id, 'event' => 'task.updated', 'before' => $before, 'after' => $task->only(['assignee_id', 'priority', 'status', 'due_at'])]);

        return back()->with('success', 'Task updated.');
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

    private function assignableUsers(Request $request)
    {
        if ($request->user()->hasPermission('projects.manage')) {
            return User::where('status', 'active')->orderBy('name')->get();
        }

        return User::whereKey($request->user()->id)->get();
    }

    private function visibleSpacesQuery(Request $request)
    {
        return Space::whereIn('id', $request->user()->visibleSpaceIds())
            ->where('active', true)
            ->orderBy('department')
            ->orderBy('name');
    }

    private function visibleProjectsQuery(Request $request)
    {
        $user = $request->user();
        $visibleSpaceIds = $user->visibleSpaceIds();

        return Project::query()->where(function ($query) use ($user, $visibleSpaceIds) {
            $query->where('owner_id', $user->id)
                ->orWhereIn('space_id', $visibleSpaceIds)
                ->orWhereHas('tasks', fn ($taskQuery) => $taskQuery->where('assignee_id', $user->id));
        });
    }

    private function userCanSeeProject(Request $request, Project $project): bool
    {
        $user = $request->user();

        if ($user->canSeeAllSpaces()) {
            return true;
        }

        return $project->owner_id === $user->id
            || in_array($project->space_id, $user->visibleSpaceIds(), true)
            || $project->tasks()->where('assignee_id', $user->id)->exists();
    }
}
