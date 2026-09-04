<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AuditPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditTrailController extends Controller
{
    public function index(Request $request, AuditPresenter $presenter): Response
    {
        $query = AuditEvent::with(['actor', 'subject'])->latest('created_at');

        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->string('actor_id'));
        }
        if ($request->filled('module')) {
            $query->where('event', 'like', $request->string('module').'.%');
        }
        if ($request->filled('event')) {
            $query->where('event', $request->string('event'));
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to'));
        }

        return Inertia::render('Admin/Audit', [
            'events' => $query->limit(100)->get()->map(fn ($event) => $presenter->present($event)),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['actor_id', 'module', 'event', 'from', 'to']),
            'modules' => ['auth', 'admin', 'request', 'workflow', 'project', 'task', 'odoo', 'system'],
        ]);
    }
}
