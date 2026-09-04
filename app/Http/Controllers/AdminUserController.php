<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Office;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function create(Request $request): Response
    {
        $employee = $request->query('employee')
            ? Employee::with(['manager', 'office'])->findOrFail($request->query('employee'))
            : null;

        if ($employee?->user_id) {
            return $this->form($employee->user()->with(['roles', 'groups', 'visibleSpaces'])->first());
        }

        return $this->form(null, $employee);
    }

    public function edit(User $user): Response
    {
        return $this->form($user->load(['roles', 'groups', 'visibleSpaces']));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['employee_id']);

        $user = User::create($data + [
            'organization_id' => $request->user()->organization_id ?? Organization::first()?->id,
            'email_verified_at' => now(),
        ]);

        $this->syncAccess($user, $request);
        $this->upsertEmployee($user, $request);
        AuditEvent::create(['actor_id' => $request->user()->id, 'subject_type' => User::class, 'subject_id' => $user->id, 'event' => 'admin.user.created', 'after' => $user->only(['name', 'email', 'status', 'account_type'])]);

        return redirect()->route('admin.index')->with('success', 'User added and access assigned.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $before = $user->only(['name', 'email', 'status', 'account_type', 'office_id', 'manager_id']);
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        unset($data['employee_id']);
        $user->update($data);

        $this->syncAccess($user, $request);
        $this->upsertEmployee($user, $request);
        AuditEvent::create(['actor_id' => $request->user()->id, 'subject_type' => User::class, 'subject_id' => $user->id, 'event' => 'admin.user.updated', 'before' => $before, 'after' => $user->only(['name', 'email', 'status', 'account_type', 'office_id', 'manager_id'])]);

        return redirect()->route('admin.index')->with('success', 'User updated.');
    }

    private function form(?User $user = null, ?Employee $employee = null): Response
    {
        return Inertia::render('Admin/UserForm', [
            'userRecord' => $user,
            'sourceEmployee' => $employee,
            'roles' => Role::orderBy('name')->get(),
            'groups' => Group::orderBy('name')->get(),
            'offices' => Office::orderBy('name')->get(),
            'managers' => User::where('status', 'active')->orderBy('name')->get(),
            'spaces' => Space::where('active', true)->orderBy('department')->orderBy('name')->get(),
            'appCatalog' => ['dashboard', 'spaces', 'employees', 'requests', 'workflows', 'projects', 'todos', 'mails', 'odoo', 'audit', 'admin'],
        ]);
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $passwordRule = $user ? ['nullable', 'string', 'min:10'] : ['required_without:invite_only', 'nullable', 'string', 'min:10'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.($user?->id ?? 'NULL').',id'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username,'.($user?->id ?? 'NULL').',id'],
            'title' => ['nullable', 'string', 'max:255'],
            'account_type' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,invited,inactive,locked'],
            'office_id' => ['nullable', 'exists:offices,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'password' => $passwordRule,
            'invite_only' => ['nullable', 'boolean'],
            'app_access' => ['array'],
            'visible_space_ids' => ['array'],
            'visible_space_ids.*' => ['exists:spaces,id'],
        ]);

        if ($request->boolean('invite_only')) {
            $data['status'] = 'invited';
            $data['password'] = $data['password'] ?: Str::password(24);
            $data['invitation_token'] = Str::random(48);
            $data['invited_at'] = now();
        }

        return $data;
    }

    private function syncAccess(User $user, Request $request): void
    {
        $user->roles()->sync($request->input('role_ids', []));
        $user->groups()->syncWithPivotValues($request->input('group_ids', []), ['membership_role' => 'member']);
        $visibleSpaceIds = $request->has('visible_space_ids')
            ? $request->input('visible_space_ids', [])
            : Space::where('active', true)->pluck('id')->all();

        $user->visibleSpaces()->sync(
            collect($visibleSpaceIds)
                ->mapWithKeys(fn ($id) => [$id => ['access_level' => 'viewer']])
                ->all()
        );
        $user->forceFill(['app_access' => $request->input('app_access', [])])->save();
    }

    private function upsertEmployee(User $user, Request $request): void
    {
        $employee = $request->filled('employee_id')
            ? Employee::find($request->input('employee_id'))
            : Employee::where('user_id', $user->id)->first();

        $attributes = [
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'manager_id' => $user->manager_id,
            'office_id' => $user->office_id,
            'name' => $user->name,
            'email' => $user->email,
            'job_title' => $user->title,
            'status' => $user->status === 'active' ? 'linked' : $user->status,
        ];

        if ($employee) {
            $employee->update($attributes);
            return;
        }

        Employee::create($attributes);
    }
}
