<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\Space;
use App\Models\Task;
use App\Models\Todo;
use App\Models\WorkflowRun;
use App\Models\WorkRequest;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user = request()->user();
        $visibleSpaceIds = $user->visibleSpaceIds();
        $visibleProjects = Project::query()
            ->where(function ($query) use ($user, $visibleSpaceIds) {
                $query->where('owner_id', $user->id)
                    ->orWhereIn('space_id', $visibleSpaceIds)
                    ->orWhereHas('tasks', fn ($taskQuery) => $taskQuery->where('assignee_id', $user->id));
            });
        $visibleProjectIds = (clone $visibleProjects)->pluck('id');

        $taskBase = Task::with(['project.space', 'assignee'])
            ->whereIn('project_id', $visibleProjectIds)
            ->latest();
        $spaces = Space::with(['owner'])
            ->withCount('projects')
            ->whereIn('id', $visibleSpaceIds)
            ->orderBy('department')
            ->orderBy('name')
            ->get()
            ->map(function (Space $space) {
                $taskCount = Task::whereHas('project', fn ($query) => $query->where('space_id', $space->id))->count();
                $dueSoon = Task::whereHas('project', fn ($query) => $query->where('space_id', $space->id))
                    ->whereNotIn('status', ['done', 'cancelled'])
                    ->whereBetween('due_at', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
                    ->count();

                return [
                    'id' => $space->id,
                    'name' => $space->name,
                    'department' => $space->department,
                    'color' => $space->color,
                    'owner' => $space->owner,
                    'projects_count' => $space->projects_count,
                    'tasks_count' => $taskCount,
                    'due_soon_count' => $dueSoon,
                ];
            });

        return Inertia::render('Dashboard', [
            'metrics' => [
                'openRequests' => WorkRequest::whereNotIn('status', ['resolved', 'cancelled'])->count(),
                'activeProjects' => (clone $visibleProjects)->where('status', 'active')->count(),
                'pendingTasks' => Task::whereIn('project_id', $visibleProjectIds)->whereNotIn('status', ['done', 'cancelled'])->count(),
                'workflowRuns' => WorkflowRun::count(),
            ],
            'allTasks' => (clone $taskBase)->limit(12)->get(),
            'myTasks' => Task::with(['project.space'])
                ->where('assignee_id', $user->id)
                ->whereNotIn('status', ['done', 'cancelled'])
                ->latest()
                ->limit(8)
                ->get(),
            'createdTasks' => Task::with(['project.space', 'assignee'])
                ->where('created_by', $user->id)
                ->latest()
                ->limit(8)
                ->get(),
            'followedTasks' => Task::with(['project.space', 'assignee'])
                ->whereIn('project_id', $visibleProjectIds)
                ->where(function ($query) use ($user) {
                    $query->where('assignee_id', $user->id)
                        ->orWhere('created_by', $user->id);
                })
                ->latest()
                ->limit(8)
                ->get(),
            'requestInbox' => [
                'pendingApproval' => WorkRequest::with(['space', 'serviceFlow', 'requester'])
                    ->whereHas('approvals', fn ($query) => $query->where('approver_id', $user->id)->where('status', 'pending'))
                    ->whereIn('status', ['submitted', 'in_review'])
                    ->latest()
                    ->limit(8)
                    ->get(),
                'submittedByMe' => WorkRequest::with(['space', 'serviceFlow'])
                    ->where('requested_by', $user->id)
                    ->latest()
                    ->limit(8)
                    ->get(),
            ],
            'spaces' => $spaces,
            'requests' => WorkRequest::with(['space', 'serviceFlow', 'requester'])->latest()->limit(6)->get(),
            'projects' => (clone $visibleProjects)->withCount('tasks')->latest()->limit(5)->get(),
            'todos' => Todo::where('user_id', $user->id)->latest()->limit(6)->get(),
            'activity' => AuditEvent::latest('created_at')->limit(8)->get(),
        ]);
    }
}
