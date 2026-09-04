<?php

namespace Database\Seeders;

use App\Models\ApprovalStep;
use App\Models\AuditEvent;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Office;
use App\Models\OdooConnection;
use App\Models\Organization;
use App\Models\OutboxEvent;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\ServiceFlow;
use App\Models\Space;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Seeder;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowRun;
use App\Models\WorkflowVersion;
use App\Models\WorkRequest;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::create([
            'name' => 'Dexterton Corporation',
            'slug' => 'dexterton',
            'timezone' => 'Asia/Manila',
            'branding' => ['primary' => '#435ebe', 'template' => 'mazer-inspired'],
        ]);

        $headOffice = Office::create(['organization_id' => $org->id, 'name' => 'Head Office', 'code' => 'HO', 'address' => 'Metro Manila', 'active' => true]);
        $itOffice = Office::create(['organization_id' => $org->id, 'name' => 'IT Office', 'code' => 'IT', 'address' => 'Dexterton IT', 'active' => true]);

        $permissions = collect([
            'admin.manage', 'users.manage', 'requests.create', 'requests.approve', 'workflows.manage',
            'projects.view', 'projects.create', 'projects.manage', 'todos.manage', 'files.manage', 'audit.view', 'api.use',
        ])->mapWithKeys(fn ($slug) => [$slug => Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => str($slug)->replace('.', ' ')->title()]
        )]);

        $ownerRole = Role::updateOrCreate(['slug' => 'system-owner'], ['name' => 'System Owner', 'protected' => true]);
        $managerRole = Role::updateOrCreate(['slug' => 'manager'], ['name' => 'Manager']);
        $memberRole = Role::updateOrCreate(['slug' => 'member'], ['name' => 'Member']);
        $projectUserRole = Role::updateOrCreate(['slug' => 'project-user'], ['name' => 'Project User']);
        $ownerRole->permissions()->sync($permissions->pluck('id'));
        $managerRole->permissions()->sync($permissions->only(['requests.approve', 'projects.view', 'projects.create', 'projects.manage', 'todos.manage', 'api.use'])->pluck('id'));
        $memberRole->permissions()->sync($permissions->only(['requests.create', 'todos.manage', 'api.use'])->pluck('id'));
        $projectUserRole->permissions()->sync($permissions->only(['projects.view', 'projects.create'])->pluck('id'));

        $manager = User::create([
            'organization_id' => $org->id,
            'office_id' => $itOffice->id,
            'name' => 'IT Manager',
            'username' => 'it.manager',
            'email' => 'it.manager@dexterton.com',
            'title' => 'IT Manager',
            'account_type' => 'manager',
            'password' => 'WorkHub2026!',
            'timezone' => 'Asia/Manila',
            'email_verified_at' => now(),
            'preferences' => ['theme' => 'light'],
        ]);

        $john = User::create([
            'organization_id' => $org->id,
            'office_id' => $itOffice->id,
            'manager_id' => $manager->id,
            'name' => 'John Raymark Llavanes',
            'username' => 'john.llavanes',
            'email' => 'john.llavanes@dexterton.com',
            'title' => 'Software Developer',
            'account_type' => 'system_owner',
            'password' => 'WorkHub2026!',
            'timezone' => 'Asia/Manila',
            'email_verified_at' => now(),
            'two_factor_enabled' => false,
            'preferences' => ['theme' => 'light', 'sidebar_collapsed' => false],
        ]);
        $john->roles()->sync([$ownerRole->id]);
        $manager->roles()->sync([$managerRole->id]);

        $admin = User::create([
            'organization_id' => $org->id,
            'office_id' => $headOffice->id,
            'name' => 'Dexterton Administrator',
            'username' => 'dexterton.admin',
            'email' => 'admin@dexterton.com',
            'title' => 'System Administrator',
            'account_type' => 'system_owner',
            'status' => 'active',
            'password' => 'DextertonAdmin2026!',
            'timezone' => 'Asia/Manila',
            'email_verified_at' => now(),
            'preferences' => ['theme' => 'light', 'sidebar_collapsed' => false],
            'app_access' => [
                'dashboard', 'spaces', 'employees', 'requests', 'workflows', 'projects', 'todos',
                'mails', 'odoo', 'audit', 'admin', 'service-flows', 'expenses', 'bookings',
                'documents', 'e-signature', 'automations', 'webforms', 'datasets',
            ],
        ]);
        $admin->roles()->sync([$ownerRole->id]);

        $projectUser = User::create([
            'organization_id' => $org->id,
            'office_id' => $itOffice->id,
            'manager_id' => $manager->id,
            'name' => 'Normal Project User',
            'username' => 'normal.project',
            'email' => 'normal.project@dexterton.com',
            'title' => 'Project Requester',
            'account_type' => 'user',
            'status' => 'active',
            'password' => 'WorkHub2026!',
            'timezone' => 'Asia/Manila',
            'email_verified_at' => now(),
            'preferences' => ['theme' => 'light'],
            'app_access' => ['dashboard', 'projects'],
        ]);
        $projectUser->roles()->sync([$projectUserRole->id]);

        foreach ([$manager, $john, $admin, $projectUser] as $user) {
            Employee::create([
                'organization_id' => $org->id,
                'user_id' => $user->id,
                'manager_id' => $user->manager_id,
                'office_id' => $user->office_id,
                'name' => $user->name,
                'email' => $user->email,
                'job_title' => $user->title,
                'department' => $user->is($projectUser) ? 'Operations' : ($user->is($admin) ? 'Administration' : 'IT'),
                'status' => 'linked',
            ]);
        }

        OdooConnection::create([
            'organization_id' => $org->id,
            'name' => 'Dexterton Odoo',
            'status' => 'not_configured',
        ]);

        foreach ([['SD Software Development', 'SD', 4], ['II IT', 'II', 10], ['DC Dexterton Corporation', 'DC', 147]] as [$name, $code]) {
            $group = Group::create(['organization_id' => $org->id, 'name' => $name, 'code' => $code]);
            $group->users()->attach($john->id, ['membership_role' => $code === 'SD' ? 'lead' : 'member']);
            $group->users()->attach($manager->id, ['membership_role' => 'manager']);
        }

        $softDevShort = Space::create([
            'organization_id' => $org->id,
            'owner_id' => $john->id,
            'name' => 'Short Term Projects',
            'slug' => 'softdev-short-term-projects',
            'department' => 'SoftDev',
            'color' => '#00bcd4',
            'description' => 'Software development requests and quick project work.',
        ]);

        Space::create([
            'organization_id' => $org->id,
            'owner_id' => $john->id,
            'name' => 'Long Term Projects',
            'slug' => 'softdev-long-term-projects',
            'department' => 'SoftDev',
            'color' => '#435ebe',
            'description' => 'Larger program and system improvement initiatives.',
        ]);

        Space::create([
            'organization_id' => $org->id,
            'owner_id' => $manager->id,
            'name' => 'Support & Maintenance',
            'slug' => 'it-support-maintenance',
            'department' => 'IT',
            'color' => '#198754',
            'description' => 'IT support, maintenance, and internal service tasks.',
        ]);

        $allSpaceAccess = Space::pluck('id')
            ->mapWithKeys(fn ($id) => [$id => ['access_level' => 'viewer']])
            ->all();

        foreach ([$manager, $john, $admin, $projectUser] as $user) {
            $user->visibleSpaces()->syncWithoutDetaching($allSpaceAccess);
        }

        $flows = collect([
            ['name' => 'IT Service Request', 'slug' => 'it-service-request', 'sla_minutes' => 1440],
            ['name' => 'Document Approval', 'slug' => 'document-approval', 'sla_minutes' => 2880],
            ['name' => 'Office Booking', 'slug' => 'office-booking', 'sla_minutes' => 720],
        ])->map(fn ($flow) => ServiceFlow::create($flow + [
            'organization_id' => $org->id,
            'owner_id' => $john->id,
            'description' => 'Configurable Rework-parity intake and routing definition.',
            'intake_schema' => ['fields' => [['key' => 'summary', 'type' => 'text', 'required' => true]]],
            'status_pipeline' => ['draft', 'submitted', 'in_review', 'approved', 'resolved', 'cancelled'],
            'status' => 'published',
            'published_at' => now(),
        ]));

        $project = Project::create([
            'organization_id' => $org->id,
            'owner_id' => $john->id,
            'space_id' => $softDevShort->id,
            'code' => 'WH-CORE',
            'name' => 'Workflow Core Pilot',
            'description' => 'Pilot build for service flows, approvals, workflows, projects, and todos.',
            'status' => 'active',
            'progress' => 42,
            'starts_at' => now()->toDateString(),
            'due_at' => now()->addMonth()->toDateString(),
        ]);

        foreach ([
            ['Map Rework request states', 'done', 'high'],
            ['Build service flow intake', 'doing', 'high'],
            ['Implement approval audit history', 'review', 'urgent'],
            ['Prepare pilot migration dry run', 'todo', 'normal'],
        ] as [$title, $status, $priority]) {
            Task::create([
                'project_id' => $project->id,
                'assignee_id' => $john->id,
                'created_by' => $john->id,
                'title' => $title,
                'description' => 'Seeded from the phased parity replacement plan.',
                'status' => $status,
                'priority' => $priority,
                'due_at' => now()->addDays(7),
            ]);
        }

        foreach ([
            ['REQ-260902-0001', 'VPN access for supplier integration', 'submitted', 'high'],
            ['REQ-260902-0002', 'Approve new workflow pilot', 'in_review', 'urgent'],
            ['REQ-260902-0003', 'Office booking for implementation workshop', 'draft', 'normal'],
        ] as [$reference, $title, $status, $priority]) {
            $request = WorkRequest::create([
                'organization_id' => $org->id,
                'service_flow_id' => $flows->first()->id,
                'space_id' => $softDevShort->id,
                'requested_by' => $john->id,
                'assigned_to' => $manager->id,
                'reference' => $reference,
                'title' => $title,
                'description' => 'Seeded request that exercises draft, review, approval, and audit behavior.',
                'status' => $status,
                'priority' => $priority,
                'form_data' => ['department' => 'IT', 'impact' => $priority],
                'submitted_at' => $status === 'draft' ? null : now()->subDay(),
                'sla_due_at' => now()->addDays(2),
            ]);
            ApprovalStep::create(['work_request_id' => $request->id, 'sequence' => 1, 'approver_id' => $manager->id, 'status' => 'pending']);
        }

        $workflow = WorkflowDefinition::create([
            'organization_id' => $org->id,
            'owner_id' => $john->id,
            'name' => 'Request Approval Escalation',
            'description' => 'Routes overdue high-priority requests to the assigned manager.',
            'status' => 'published',
            'current_version' => 1,
        ]);
        $version = WorkflowVersion::create([
            'workflow_definition_id' => $workflow->id,
            'version' => 1,
            'trigger' => ['type' => 'request.sla_due'],
            'nodes' => [
                ['id' => 'start', 'type' => 'trigger', 'label' => 'SLA due'],
                ['id' => 'condition', 'type' => 'condition', 'label' => 'Priority is high'],
                ['id' => 'notify', 'type' => 'notification', 'label' => 'Notify manager'],
            ],
            'edges' => [['from' => 'start', 'to' => 'condition'], ['from' => 'condition', 'to' => 'notify']],
            'published_by' => $john->id,
            'published_at' => now(),
        ]);
        WorkflowRun::create([
            'workflow_version_id' => $version->id,
            'started_by' => $john->id,
            'status' => 'completed',
            'context' => ['request_reference' => 'REQ-260902-0002'],
            'result' => ['notified' => 'it.manager@dexterton.com'],
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(9),
        ]);

        foreach (['Review parity catalog', 'Check approval edge cases', 'Prepare pilot user list'] as $title) {
            Todo::create(['user_id' => $john->id, 'title' => $title, 'priority' => 'normal', 'due_at' => now()->addDays(3)]);
        }

        foreach (['system.seeded', 'parity.catalog.created', 'workflow.version.published'] as $event) {
            AuditEvent::create(['actor_id' => $john->id, 'event' => $event, 'after' => ['source' => 'DatabaseSeeder']]);
        }
        OutboxEvent::create([
            'topic' => 'notification.request.seeded',
            'aggregate_type' => WorkRequest::class,
            'aggregate_id' => WorkRequest::first()->id,
            'payload' => ['channels' => ['in_app', 'email'], 'status' => 'queued'],
        ]);
    }
}
