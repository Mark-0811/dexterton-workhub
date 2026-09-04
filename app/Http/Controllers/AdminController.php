<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Group;
use App\Models\Office;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use App\Services\AuditPresenter;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function __invoke(AuditPresenter $presenter): Response
    {
        return Inertia::render('Admin/Index', [
            'users' => User::with(['office', 'roles', 'groups'])->orderBy('name')->get(),
            'offices' => Office::orderBy('name')->get(),
            'groups' => Group::withCount('users')->orderBy('name')->get(),
            'roles' => Role::with('permissions')->orderBy('name')->get(),
            'spaces' => Space::with('owner')
                ->withCount('projects')
                ->orderBy('department')
                ->orderBy('name')
                ->get(),
            'auditEvents' => AuditEvent::with(['actor', 'subject'])->latest('created_at')->limit(20)->get()->map(fn ($event) => $presenter->present($event)),
        ]);
    }
}
