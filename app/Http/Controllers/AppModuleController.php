<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppModuleController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $modules = [
            'service-flows' => ['title' => 'Service Flows', 'phase' => 'Workflow Core', 'status' => 'Catalog ready', 'icon' => 'bi-bezier2'],
            'expenses' => ['title' => 'Expenses', 'phase' => 'Operations and Collaboration', 'status' => 'Planned', 'icon' => 'bi-receipt-cutoff'],
            'bookings' => ['title' => 'Bookings', 'phase' => 'Operations and Collaboration', 'status' => 'Planned', 'icon' => 'bi-calendar2-check-fill'],
            'documents' => ['title' => 'Documents', 'phase' => 'Operations and Collaboration', 'status' => 'Planned', 'icon' => 'bi-file-earmark-richtext-fill'],
            'e-signature' => ['title' => 'E-Signature', 'phase' => 'Platform Services', 'status' => 'Planned', 'icon' => 'bi-pen-fill'],
            'automations' => ['title' => 'Automations', 'phase' => 'Platform Services', 'status' => 'Planned', 'icon' => 'bi-robot'],
            'webforms' => ['title' => 'Webforms', 'phase' => 'Platform Services', 'status' => 'Planned', 'icon' => 'bi-ui-checks-grid'],
            'mails' => ['title' => 'Mails', 'phase' => 'Operations and Collaboration', 'status' => 'Planned', 'icon' => 'bi-envelope-paper-fill'],
            'datasets' => ['title' => 'Datasets', 'phase' => 'Platform Services', 'status' => 'Planned', 'icon' => 'bi-database-fill'],
        ];

        abort_unless(isset($modules[$slug]), 404);
        $appAccess = $request->user()->app_access;
        abort_unless(! is_array($appAccess) || $appAccess === [] || in_array($slug, $appAccess, true), 403);

        return Inertia::render('Apps/Show', [
            'module' => $modules[$slug],
            'slug' => $slug,
        ]);
    }
}
