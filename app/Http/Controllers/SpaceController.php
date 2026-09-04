<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\Space;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SpaceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Spaces/Index', [
            'spaces' => Space::with(['owner'])->withCount(['projects'])->orderBy('department')->orderBy('name')->get(),
            'users' => User::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, Space $space): Response
    {
        abort_unless($space->active, 404);
        abort_unless($request->user()->canSeeAllSpaces() || in_array($space->id, $request->user()->visibleSpaceIds(), true), 403);

        $projects = Project::with(['owner'])
            ->withCount('tasks')
            ->where('space_id', $space->id)
            ->latest()
            ->get();

        $tasks = Task::with(['project', 'assignee'])
            ->whereHas('project', fn ($query) => $query->where('space_id', $space->id))
            ->latest()
            ->limit(30)
            ->get();

        return Inertia::render('Spaces/Show', [
            'space' => $space->load('owner'),
            'metrics' => [
                'projects' => $projects->count(),
                'activeProjects' => $projects->where('status', 'active')->count(),
                'openTasks' => $tasks->whereNotIn('status', ['done', 'cancelled'])->count(),
                'openRequests' => WorkRequest::where('space_id', $space->id)->whereNotIn('status', ['approved', 'rejected', 'cancelled'])->count(),
            ],
            'projects' => $projects,
            'tasks' => $tasks,
            'requests' => WorkRequest::with(['requester', 'serviceFlow', 'project'])
                ->where('space_id', $space->id)
                ->latest()
                ->limit(12)
                ->get(),
            'members' => User::whereHas('visibleSpaces', fn ($query) => $query->where('spaces.id', $space->id))
                ->orWhere('id', $space->owner_id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'description' => ['nullable', 'string'],
            'active' => ['required', 'boolean'],
        ]);

        $space = Space::create($data + [
            'organization_id' => $request->user()->organization_id,
            'slug' => $this->uniqueSlug($data['department'].' '.$data['name']),
        ]);

        AuditEvent::create([
            'actor_id' => $request->user()->id,
            'subject_type' => Space::class,
            'subject_id' => $space->id,
            'event' => 'space.created',
            'after' => $space->only(['name', 'department', 'owner_id', 'color', 'active']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Space created.');
    }

    private function uniqueSlug(string $value): string
    {
        $base = Str::slug($value);
        $slug = $base;
        $next = 2;

        while (Space::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$next}";
            $next++;
        }

        return $slug;
    }
}
