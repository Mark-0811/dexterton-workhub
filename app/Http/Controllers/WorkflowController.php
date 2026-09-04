<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowRun;
use App\Models\WorkflowVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Workflows/Index', [
            'workflows' => WorkflowDefinition::with('versions')->latest()->get(),
            'runs' => WorkflowRun::latest()->limit(10)->get(),
        ]);
    }

    public function show(WorkflowDefinition $workflow): Response
    {
        return Inertia::render('Workflows/Show', [
            'workflow' => $workflow->load('versions'),
        ]);
    }

    public function run(Request $request, WorkflowDefinition $workflow): RedirectResponse
    {
        $version = $workflow->versions()->latest('version')->firstOrFail();
        $run = WorkflowRun::create([
            'workflow_version_id' => $version->id,
            'started_by' => $request->user()->id,
            'status' => 'completed',
            'context' => ['manual' => true],
            'result' => ['message' => 'Demo execution completed.'],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        AuditEvent::create(['actor_id' => $request->user()->id, 'subject_type' => WorkflowRun::class, 'subject_id' => $run->id, 'event' => 'workflow.run']);

        return back()->with('success', 'Workflow run completed.');
    }
}
