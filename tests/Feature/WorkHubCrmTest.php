<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\AppSetting;
use App\Models\AppNotification;
use App\Models\DatasetReport;
use App\Models\Employee;
use App\Models\OdooConnection;
use App\Models\OutboxEvent;
use App\Models\Project;
use App\Models\Role;
use App\Models\ServiceFlow;
use App\Models\Space;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkHubCrmTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_and_app_tile_routes_open(): void
    {
        $this->seed();
        $admin = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();

        foreach ([
            '/dashboard',
            '/profile',
            '/spaces',
            '/employees',
            '/requests',
            '/workflows',
            '/projects',
            '/todos',
            '/admin',
            '/admin/audit',
            '/admin/mail-settings',
            '/integrations/odoo',
            '/apps/service-flows',
            '/apps/expenses',
            '/apps/bookings',
            '/apps/documents',
            '/apps/e-signature',
            '/apps/automations',
            '/apps/webforms',
            '/apps/mails',
            '/apps/datasets',
        ] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_admin_can_create_user_with_roles_and_app_access(): void
    {
        $this->seed();
        $admin = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();
        $role = Role::where('slug', 'member')->firstOrFail();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Pilot Employee',
            'email' => 'pilot.employee@dexterton.com',
            'username' => 'pilot.employee',
            'title' => 'Pilot User',
            'account_type' => 'user',
            'status' => 'active',
            'password' => 'Temporary2026!',
            'role_ids' => [$role->id],
            'group_ids' => [],
            'app_access' => ['dashboard', 'projects', 'todos'],
        ])->assertRedirect('/admin');

        $user = User::where('email', 'pilot.employee@dexterton.com')->firstOrFail();
        $this->assertTrue($user->roles()->where('slug', 'member')->exists());
        $this->assertSame(['dashboard', 'projects', 'todos'], $user->app_access);
        $this->assertDatabaseHas('employees', ['user_id' => $user->id, 'status' => 'linked']);
    }

    public function test_admin_can_create_user_from_employee_record(): void
    {
        $this->seed();
        $admin = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();
        $role = Role::where('slug', 'member')->firstOrFail();
        $space = Space::firstOrFail();
        $employee = Employee::create([
            'organization_id' => $admin->organization_id,
            'external_source' => 'odoo',
            'external_id' => 'EMP-CREATE-USER',
            'name' => 'Odoo Provisional Employee',
            'email' => 'odoo.provisional@dexterton.com',
            'job_title' => 'Project Coordinator',
            'department' => 'Operations',
            'status' => 'provisional',
        ]);

        $this->actingAs($admin)->get("/admin/users/create?employee={$employee->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sourceEmployee.id', $employee->id)
                ->where('sourceEmployee.name', 'Odoo Provisional Employee')
            );

        $this->actingAs($admin)->post('/admin/users', [
            'employee_id' => $employee->id,
            'name' => 'Odoo Provisional Employee',
            'email' => 'odoo.provisional@dexterton.com',
            'username' => 'odoo.provisional',
            'title' => 'Project Coordinator',
            'account_type' => 'user',
            'status' => 'active',
            'password' => 'Temporary2026!',
            'role_ids' => [$role->id],
            'group_ids' => [],
            'app_access' => ['dashboard', 'projects', 'todos'],
            'visible_space_ids' => [$space->id],
        ])->assertRedirect('/admin');

        $user = User::where('email', 'odoo.provisional@dexterton.com')->firstOrFail();
        $employee->refresh();

        $this->assertSame($user->id, $employee->user_id);
        $this->assertSame('linked', $employee->status);
        $this->assertSame('Project Coordinator', $user->title);
        $this->assertTrue($user->visibleSpaces()->where('spaces.id', $space->id)->exists());
    }

    public function test_request_app_grant_shows_on_profile_and_allows_request_creation_in_space(): void
    {
        $this->seed();
        $organization = Organization::firstOrFail();
        $visibleSpace = Space::where('slug', 'softdev-short-term-projects')->firstOrFail();
        $requestOnlySpace = Space::where('slug', 'softdev-long-term-projects')->firstOrFail();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Request Access User',
            'email' => 'request.access@dexterton.com',
            'status' => 'active',
            'app_access' => ['dashboard', 'requests'],
        ]);
        $user->visibleSpaces()->sync([$visibleSpace->id => ['access_level' => 'viewer']]);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('accessSummary.apps.1', 'requests')
                ->where('accessSummary.permissions.0', 'requests.create')
                ->where('accessSummary.spaces.0.id', $visibleSpace->id)
            );

        $this->actingAs($user)->get('/requests')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('spaces', Space::where('active', true)->count())
            );

        $this->actingAs($user)->post('/requests', [
            'title' => 'Space-directed request',
            'description' => 'Please route this to the selected Space.',
            'service_flow_id' => '',
            'space_id' => $requestOnlySpace->id,
            'priority' => 'normal',
        ])->assertRedirect();

        $this->assertDatabaseHas('work_requests', [
            'title' => 'Space-directed request',
            'space_id' => $requestOnlySpace->id,
            'requested_by' => $user->id,
            'status' => 'draft',
        ]);
    }

    public function test_audit_trail_page_uses_readable_events(): void
    {
        $this->seed();
        $admin = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();

        $this->actingAs($admin)->get('/admin/audit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Audit')
                ->has('events')
            );
    }

    public function test_admin_can_open_datasets_and_create_report(): void
    {
        $this->seed();
        $admin = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();

        $this->actingAs($admin)->get('/apps/datasets')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Datasets/Index')
                ->has('sources.projects')
                ->has('reports')
            );

        $this->actingAs($admin)->post('/apps/datasets/reports', [
            'name' => 'Active Projects by Space',
            'source' => 'projects',
            'description' => 'Shows active project ownership and status.',
            'columns' => ['code', 'name', 'owner', 'space', 'status', 'progress'],
            'filter_notes' => 'active only',
            'visibility' => 'managers',
        ])->assertRedirect();

        $report = DatasetReport::where('name', 'Active Projects by Space')->firstOrFail();

        $this->assertSame('projects', $report->source);
        $this->assertContains('space', $report->columns);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $report->id, 'event' => 'dataset.report_created']);
    }

    public function test_odoo_csv_import_upserts_employee_project_and_task(): void
    {
        $this->seed();
        Storage::fake('local');
        $admin = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();
        $csv = "type,external_id,name,email,job_title,department,project_external_id,project_name,title,assignee_email,due_at,status,priority\n"
            ."employee,E001,Imported Employee,imported.employee@dexterton.com,Analyst,IT,,,,,,active,normal\n"
            ."project,P001,Odoo Pilot,,,,,,Odoo Pilot,,,,active,normal\n"
            ."task,T001,Imported Task,,,,P001,Odoo Pilot,Imported Task,john.llavanes@dexterton.com,2026-09-30,todo,high\n";
        $file = UploadedFile::fake()->createWithContent('odoo.csv', $csv);

        $response = $this->actingAs($admin)->post('/integrations/odoo/imports', ['file' => $file]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employees', ['external_id' => 'E001', 'name' => 'Imported Employee']);
        $this->assertDatabaseHas('projects', ['external_id' => 'P001', 'name' => 'Odoo Pilot']);
        $this->assertDatabaseHas('tasks', ['external_id' => 'T001', 'title' => 'Imported Task']);
    }

    public function test_odoo_api_sync_accepts_odoo_13_task_user_id_field(): void
    {
        $this->seed();
        $admin = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();
        $organization = Organization::firstOrFail();

        OdooConnection::query()->updateOrCreate(['organization_id' => $organization->id], [
            'organization_id' => $organization->id,
            'base_url' => 'https://odoo.example.test',
            'database' => 'dexterton',
            'username' => 'api@example.test',
            'api_key' => 'secret',
            'status' => 'configured',
        ]);

        Http::fake([
            'odoo.example.test/jsonrpc' => Http::sequence()
                ->push(['result' => 7])
                ->push(['result' => ['id' => [], 'name' => [], 'work_email' => [], 'job_title' => [], 'department_id' => []]])
                ->push(['result' => [['id' => 501, 'name' => 'Odoo Employee', 'work_email' => 'john.llavanes@dexterton.com', 'job_title' => 'Developer', 'department_id' => [10, 'SoftDev']]]])
                ->push(['result' => ['id' => [], 'name' => [], 'date' => [], 'user_id' => []]])
                ->push(['result' => [['id' => 601, 'name' => 'Odoo Project', 'date' => '2026-10-01', 'user_id' => false]]])
                ->push(['result' => ['id' => [], 'name' => [], 'project_id' => [], 'user_id' => [], 'date_deadline' => [], 'stage_id' => []]])
                ->push(['result' => [['id' => 701, 'name' => 'Odoo 13 Task', 'project_id' => [601, 'Odoo Project'], 'user_id' => [901, 'John'], 'date_deadline' => '2026-09-30', 'stage_id' => [3, 'In Progress']]]])
                ->push(['result' => ['id' => [], 'login' => [], 'email' => []]])
                ->push(['result' => [['id' => 901, 'login' => 'john.llavanes@dexterton.com', 'email' => 'john.llavanes@dexterton.com']]]),
        ]);

        $this->actingAs($admin)->post('/integrations/odoo/sync')->assertRedirect();

        $this->assertDatabaseHas('projects', ['external_source' => 'odoo', 'external_id' => '601', 'name' => 'Odoo Project']);
        $this->assertDatabaseHas('tasks', ['external_source' => 'odoo', 'external_id' => '701', 'title' => 'Odoo 13 Task', 'status' => 'doing', 'assignee_id' => $admin->id]);
    }

    public function test_admin_can_assign_project_owner_and_task_assignee(): void
    {
        $this->seed();
        $organization = Organization::firstOrFail();
        $admin = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();
        $assignee = User::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Assigned Employee',
            'email' => 'assigned.employee@dexterton.com',
            'status' => 'active',
        ]);

        $project = Project::create([
            'organization_id' => $organization->id,
            'owner_id' => $admin->id,
            'code' => 'CRM-ASSIGN',
            'name' => 'CRM Assignment Pilot',
            'description' => 'Assignment test project',
            'status' => 'active',
            'progress' => 5,
        ]);

        $this->actingAs($admin)->patch("/projects/{$project->id}", [
            'owner_id' => $assignee->id,
            'status' => 'on_hold',
            'progress' => 35,
            'due_at' => '2026-09-30',
        ])->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'owner_id' => $assignee->id,
            'status' => 'on_hold',
            'progress' => 35,
        ]);

        $this->actingAs($admin)->post("/projects/{$project->id}/tasks", [
            'title' => 'Assign actual work',
            'description' => 'This task should be owned by the selected employee.',
            'assignee_id' => $assignee->id,
            'priority' => 'high',
            'status' => 'todo',
            'due_at' => '2026-09-29',
        ])->assertRedirect();

        $task = Task::where('title', 'Assign actual work')->firstOrFail();

        $this->actingAs($admin)->patch("/projects/{$project->id}/tasks/{$task->id}", [
            'assignee_id' => $admin->id,
            'priority' => 'urgent',
            'status' => 'doing',
            'due_at' => '2026-10-01',
        ])->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assignee_id' => $admin->id,
            'priority' => 'urgent',
            'status' => 'doing',
        ]);
    }

    public function test_project_code_is_generated_when_left_blank(): void
    {
        $this->seed();
        $admin = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();

        $this->actingAs($admin)->post('/projects', [
            'code' => '',
            'name' => 'Auto Numbered Project',
            'description' => 'Project code should be generated automatically.',
            'owner_id' => $admin->id,
            'status' => 'active',
            'due_at' => '2026-10-15',
        ])->assertRedirect();

        $project = Project::where('name', 'Auto Numbered Project')->firstOrFail();

        $this->assertMatchesRegularExpression('/^PRJ-\d{5}$/', $project->code);
    }

    public function test_approved_request_can_create_a_linked_project(): void
    {
        $this->seed();
        $admin = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();
        $space = Space::firstOrFail();
        $flow = ServiceFlow::first();

        $requestRecord = WorkRequest::create([
            'organization_id' => $admin->organization_id,
            'service_flow_id' => $flow?->id,
            'requested_by' => $admin->id,
            'reference' => 'REQ-PROJECT-1',
            'title' => 'Convert this request',
            'description' => 'This should become a project.',
            'status' => 'approved',
            'priority' => 'high',
            'resolved_at' => now(),
        ]);

        $this->actingAs($admin)->post("/requests/{$requestRecord->id}/project", [
            'name' => 'Project From Approved Request',
            'description' => 'Created from request.',
            'owner_id' => $admin->id,
            'space_id' => $space->id,
            'due_at' => '2026-10-15T09:30',
        ])->assertRedirect();

        $project = Project::where('name', 'Project From Approved Request')->firstOrFail();

        $this->assertSame($project->id, $requestRecord->fresh()->project_id);
        $this->assertSame('work_request', $project->settings['source']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $requestRecord->id, 'event' => 'request.project_created']);
    }

    public function test_normal_project_user_can_create_and_select_projects_only(): void
    {
        $this->seed();
        $user = User::where('email', 'normal.project@dexterton.com')->firstOrFail();
        $space = Space::firstOrFail();
        $project = Project::firstOrFail();

        $this->actingAs($user)->get('/projects')->assertOk();
        $this->actingAs($user)->get("/projects/{$project->id}")->assertOk();

        $this->actingAs($user)->post('/projects', [
            'code' => '',
            'name' => 'Normal User Project',
            'description' => 'Created by a restricted project user.',
            'owner_id' => User::where('email', 'john.llavanes@dexterton.com')->firstOrFail()->id,
            'space_id' => $space->id,
            'status' => 'active',
            'due_at' => '2026-10-20',
        ])->assertRedirect();

        $created = Project::where('name', 'Normal User Project')->firstOrFail();
        $this->assertSame($user->id, $created->owner_id);
        $this->assertSame($space->id, $created->space_id);

        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs($user)->get('/employees')->assertForbidden();
        $this->actingAs($user)->get('/integrations/odoo')->assertForbidden();
        $this->actingAs($user)->post("/projects/{$created->id}/tasks", [
            'title' => 'Should not be allowed',
            'priority' => 'normal',
            'status' => 'todo',
        ])->assertForbidden();
        $this->actingAs($user)->patch("/projects/{$created->id}", [
            'owner_id' => $user->id,
            'status' => 'on_hold',
            'progress' => 20,
        ])->assertForbidden();
    }

    public function test_admin_can_limit_user_to_specific_spaces_and_user_still_sees_their_projects(): void
    {
        $this->seed();

        $admin = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();
        $user = User::where('email', 'normal.project@dexterton.com')->firstOrFail();
        $allowedSpace = Space::where('slug', 'softdev-short-term-projects')->firstOrFail();
        $blockedSpace = Space::where('slug', 'softdev-long-term-projects')->firstOrFail();

        $this->actingAs($admin)->patch("/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'title' => $user->title,
            'account_type' => $user->account_type,
            'status' => 'active',
            'office_id' => $user->office_id,
            'manager_id' => $user->manager_id,
            'role_ids' => $user->roles()->pluck('id')->all(),
            'group_ids' => [],
            'app_access' => ['dashboard', 'projects'],
            'visible_space_ids' => [$allowedSpace->id],
        ])->assertRedirect();

        $ownedBlockedProject = Project::create([
            'organization_id' => $user->organization_id,
            'owner_id' => $user->id,
            'space_id' => $blockedSpace->id,
            'code' => 'OWN-BLOCKED-SPACE',
            'name' => 'Owned Project In Hidden Space',
            'status' => 'active',
            'progress' => 0,
        ]);

        Project::create([
            'organization_id' => $admin->organization_id,
            'owner_id' => $admin->id,
            'space_id' => $blockedSpace->id,
            'code' => 'ADMIN-BLOCKED-SPACE',
            'name' => 'Admin Project In Hidden Space',
            'status' => 'active',
            'progress' => 0,
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('sidebarSpaces', 1)
                ->where('sidebarSpaces.0.id', $allowedSpace->id)
            );

        $this->actingAs($user)->get('/projects')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('spaces', 1)
                ->where('spaces.0.id', $allowedSpace->id)
                ->has('projects')
            );

        $this->actingAs($user)->get("/projects?space={$allowedSpace->id}")->assertOk();

        $this->actingAs($user)->get("/projects?space={$blockedSpace->id}")->assertForbidden();
        $this->actingAs($user)->get("/projects/{$ownedBlockedProject->id}")->assertOk();

        $this->actingAs($user)->post('/projects', [
            'code' => '',
            'name' => 'Should Not Enter Hidden Space',
            'space_id' => $blockedSpace->id,
            'status' => 'active',
        ])->assertSessionHasErrors('space_id');
    }

    public function test_seeded_dexterton_administrator_can_open_employee_admin_pages(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@dexterton.com')->firstOrFail();

        $this->actingAs($admin)->get('/employees')->assertOk();
        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/spaces')->assertOk();
    }

    public function test_admin_can_configure_mail_alerts_and_board_colors(): void
    {
        $this->seed();
        $admin = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();

        $this->actingAs($admin)->patch('/admin/mail-settings', [
            'enabled' => true,
            'smtp_host' => 'smtp.dexterton.com',
            'smtp_port' => 587,
            'smtp_username' => 'workhub@dexterton.com',
            'smtp_from_address' => 'workhub@dexterton.com',
            'smtp_from_name' => 'Dexterton WorkHub',
            'alert_days_before' => 5,
            'send_time' => now()->format('H:i'),
            'recipients' => ['owner' => true, 'assignee' => true, 'manager' => false],
            'task_colors' => [
                'todo' => '#eaf1ff',
                'doing' => '#fff3cd',
                'review' => '#ede7ff',
                'done' => '#d1e7dd',
                'cancelled' => '#f8d7da',
            ],
        ])->assertRedirect();

        $this->assertSame('smtp.dexterton.com', AppSetting::getValue('mail.deadline_alerts')['smtp_host']);
        $this->assertSame('#d1e7dd', AppSetting::getValue('task.board_colors')['done']);
    }

    public function test_user_can_update_their_profile_settings(): void
    {
        $this->seed();
        $user = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();

        $this->actingAs($user)->patch('/profile', [
            'name' => 'John Raymark Llavanes',
            'username' => 'john.raymark',
            'title' => 'Senior Software Developer',
            'timezone' => 'Asia/Singapore',
            'preferences' => [
                'theme' => 'dark',
                'email_deadline_alerts' => true,
                'in_app_notifications' => true,
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'title' => 'Senior Software Developer',
            'timezone' => 'Asia/Singapore',
        ]);

        $this->assertSame('dark', $user->fresh()->preferences['theme']);
    }

    public function test_user_can_create_department_space(): void
    {
        $this->seed();
        $admin = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();

        $this->actingAs($admin)->post('/spaces', [
            'name' => 'Short Term Projects',
            'department' => 'Operations',
            'owner_id' => $admin->id,
            'color' => '#00bcd4',
            'description' => 'Operations short term work.',
            'active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('spaces', [
            'department' => 'Operations',
            'name' => 'Short Term Projects',
            'owner_id' => $admin->id,
        ]);
    }

    public function test_opened_notifications_can_be_marked_as_read(): void
    {
        $this->seed();
        $user = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();

        $notification = AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Project deadline near',
            'body' => 'A project is close to its due date.',
            'href' => '/projects',
            'category' => 'project',
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('notifications', 1));

        $this->actingAs($user)->post('/notifications/read')->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('notifications', 0));
    }

    public function test_deadline_alert_command_queues_project_and_task_mail_outbox(): void
    {
        $this->seed();
        $organization = Organization::firstOrFail();
        $admin = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();

        AppSetting::setValue('mail.deadline_alerts', [
            'enabled' => true,
            'smtp_host' => 'smtp.dexterton.com',
            'smtp_port' => 587,
            'smtp_username' => 'workhub@dexterton.com',
            'smtp_from_address' => 'workhub@dexterton.com',
            'smtp_from_name' => 'Dexterton WorkHub',
            'alert_days_before' => 7,
            'send_time' => now()->format('H:i'),
            'recipients' => ['owner' => true, 'assignee' => true, 'manager' => false],
        ], $admin->id);

        $project = Project::create([
            'organization_id' => $organization->id,
            'owner_id' => $admin->id,
            'code' => 'DEADLINE-1',
            'name' => 'Deadline Project',
            'status' => 'active',
            'progress' => 10,
            'due_at' => now()->addDays(2)->toDateString(),
        ]);

        Task::create([
            'project_id' => $project->id,
            'created_by' => $admin->id,
            'assignee_id' => $admin->id,
            'title' => 'Deadline Task',
            'status' => 'todo',
            'priority' => 'high',
            'due_at' => now()->addDays(2),
        ]);

        $this->artisan('workhub:deadline-alerts')->assertExitCode(0);

        $this->assertDatabaseHas('outbox_events', ['topic' => 'project.deadline.near', 'aggregate_id' => $project->id]);
        $this->assertTrue(OutboxEvent::where('topic', 'task.deadline.near')->where('payload->task_title', 'Deadline Task')->exists());
    }
}
