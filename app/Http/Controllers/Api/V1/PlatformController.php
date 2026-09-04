<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Group;
use App\Models\Project;
use App\Models\Todo;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkRequest;

class PlatformController extends Controller
{
    public function catalog(): array
    {
        return [
            'version' => 'v1',
            'resources' => [
                'users' => route('api.v1.users'),
                'groups' => route('api.v1.groups'),
                'projects' => route('api.v1.projects'),
                'requests' => route('api.v1.requests'),
                'workflows' => route('api.v1.workflows'),
                'todos' => route('api.v1.todos'),
                'audit' => route('api.v1.audit'),
            ],
        ];
    }

    public function users()
    {
        return User::with(['office', 'roles', 'groups'])->paginate(25);
    }

    public function groups()
    {
        return Group::withCount('users')->paginate(25);
    }

    public function projects()
    {
        return Project::withCount('tasks')->paginate(25);
    }

    public function requests()
    {
        return WorkRequest::with('approvals')->paginate(25);
    }

    public function workflows()
    {
        return WorkflowDefinition::with('versions')->paginate(25);
    }

    public function todos()
    {
        return Todo::paginate(25);
    }

    public function audit()
    {
        return AuditEvent::latest('created_at')->paginate(25);
    }
}
