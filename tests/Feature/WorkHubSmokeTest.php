<?php

namespace Tests\Feature;

use App\Models\Todo;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowRun;
use App\Models\WorkRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkHubSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_system_owner_can_open_dashboard(): void
    {
        $this->seed();
        $user = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertTrue($user->roles()->where('slug', 'system-owner')->exists());
    }

    public function test_api_catalog_exposes_versioned_resources(): void
    {
        $this->getJson('/api/v1')
            ->assertOk()
            ->assertJsonPath('version', 'v1')
            ->assertJsonStructure(['resources' => ['users', 'groups', 'projects', 'requests', 'workflows', 'todos', 'audit']]);
    }

    public function test_request_transition_records_status_change(): void
    {
        $this->seed();
        $user = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();
        $request = WorkRequest::where('status', 'draft')->firstOrFail();

        $this->actingAs($user)->post("/requests/{$request->id}/submit")->assertRedirect();

        $this->assertSame('submitted', $request->fresh()->status);
    }

    public function test_workflow_can_create_a_run(): void
    {
        $this->seed();
        $user = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();
        $workflow = WorkflowDefinition::firstOrFail();

        $this->actingAs($user)->post("/workflows/{$workflow->id}/run")->assertRedirect();

        $this->assertGreaterThan(1, WorkflowRun::count());
    }

    public function test_user_can_toggle_todo_completion(): void
    {
        $this->seed();
        $user = User::where('email', 'john.llavanes@dexterton.com')->firstOrFail();
        $todo = Todo::where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)->post("/todos/{$todo->id}/toggle")->assertRedirect();

        $this->assertNotNull($todo->fresh()->completed_at);
    }
}
