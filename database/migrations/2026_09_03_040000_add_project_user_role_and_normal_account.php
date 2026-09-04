<?php

use App\Models\Employee;
use App\Models\Office;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $projectView = Permission::firstOrCreate(
            ['slug' => 'projects.view'],
            ['name' => 'Projects View']
        );
        $projectCreate = Permission::firstOrCreate(
            ['slug' => 'projects.create'],
            ['name' => 'Projects Create']
        );

        $projectUserRole = Role::firstOrCreate(
            ['slug' => 'project-user'],
            ['name' => 'Project User', 'protected' => false]
        );
        $projectUserRole->permissions()->syncWithoutDetaching([$projectView->id, $projectCreate->id]);

        $managerRole = Role::where('slug', 'manager')->first();
        if ($managerRole) {
            $managerRole->permissions()->syncWithoutDetaching([$projectView->id, $projectCreate->id]);
        }

        $organization = Organization::first();
        if (! $organization) {
            return;
        }

        $office = Office::where('organization_id', $organization->id)->orderBy('name')->first();
        $manager = User::where('organization_id', $organization->id)
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['manager', 'system-owner']))
            ->orderBy('name')
            ->first();

        $user = User::firstOrCreate(
            ['email' => 'normal.project@dexterton.com'],
            [
                'organization_id' => $organization->id,
                'office_id' => $office?->id,
                'manager_id' => $manager?->id,
                'name' => 'Normal Project User',
                'username' => 'normal.project',
                'title' => 'Project Requester',
                'account_type' => 'user',
                'status' => 'active',
                'password' => 'WorkHub2026!',
                'timezone' => 'Asia/Manila',
                'email_verified_at' => now(),
                'preferences' => ['theme' => 'light'],
                'app_access' => ['dashboard', 'projects'],
            ]
        );

        $user->forceFill([
            'organization_id' => $user->organization_id ?: $organization->id,
            'office_id' => $user->office_id ?: $office?->id,
            'manager_id' => $user->manager_id ?: $manager?->id,
            'status' => 'active',
            'app_access' => ['dashboard', 'projects'],
        ])->save();
        $user->roles()->syncWithoutDetaching([$projectUserRole->id]);

        Employee::firstOrCreate(
            ['user_id' => $user->id],
            [
                'organization_id' => $organization->id,
                'manager_id' => $user->manager_id,
                'office_id' => $user->office_id,
                'name' => $user->name,
                'email' => $user->email,
                'job_title' => $user->title,
                'department' => 'Operations',
                'status' => 'linked',
            ]
        );
    }

    public function down(): void
    {
        $role = Role::where('slug', 'project-user')->first();
        $user = User::where('email', 'normal.project@dexterton.com')->first();

        if ($role && $user) {
            $user->roles()->detach($role->id);
        }
    }
};
