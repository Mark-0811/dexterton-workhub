<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AppModuleController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatasetReportController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\MailSettingsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OdooIntegrationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SpaceController;
use App\Http\Controllers\TodoController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\WorkRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/notifications/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::middleware('permission:projects.view')->group(function () {
        Route::get('/spaces', [SpaceController::class, 'index'])->name('spaces.index');
        Route::get('/spaces/{space}', [SpaceController::class, 'show'])->name('spaces.show');
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    });
    Route::post('/spaces', [SpaceController::class, 'store'])->middleware('permission:projects.manage')->name('spaces.store');
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('permission:projects.create')->name('projects.store');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->middleware('permission:projects.manage')->name('projects.update');
    Route::post('/projects/{project}/tasks', [ProjectController::class, 'storeTask'])->middleware('permission:projects.manage')->name('projects.tasks.store');
    Route::patch('/projects/{project}/tasks/{task}', [ProjectController::class, 'updateTask'])->middleware('permission:projects.manage')->name('projects.tasks.update');

    Route::middleware('permission:requests.create')->group(function () {
        Route::resource('requests', WorkRequestController::class)->parameters(['requests' => 'requestRecord'])->only(['index', 'show', 'store']);
    });
    Route::post('/requests/{requestRecord}/project', [WorkRequestController::class, 'createProject'])->middleware('permission:projects.create')->name('requests.project.create');
    Route::post('/requests/{requestRecord}/task', [WorkRequestController::class, 'createTask'])->middleware('permission:projects.manage')->name('requests.task.create');
    Route::post('/requests/{requestRecord}/{action}', [WorkRequestController::class, 'transition'])->name('requests.transition');

    Route::resource('workflows', WorkflowController::class)->middleware('permission:workflows.manage')->only(['index', 'show']);
    Route::post('/workflows/{workflow}/run', [WorkflowController::class, 'run'])->middleware('permission:workflows.manage')->name('workflows.run');

    Route::middleware('permission:todos.manage')->group(function () {
        Route::get('/todos', [TodoController::class, 'index'])->name('todos.index');
        Route::post('/todos', [TodoController::class, 'store'])->name('todos.store');
        Route::post('/todos/{todo}/toggle', [TodoController::class, 'toggle'])->name('todos.toggle');
    });

    Route::get('/employees', [EmployeeController::class, 'index'])->middleware('permission:users.manage')->name('employees.index');
    Route::get('/admin', AdminController::class)->middleware('permission:admin.manage')->name('admin.index');
    Route::get('/admin/users/create', [AdminUserController::class, 'create'])->middleware('permission:users.manage')->name('admin.users.create');
    Route::post('/admin/users', [AdminUserController::class, 'store'])->middleware('permission:users.manage')->name('admin.users.store');
    Route::get('/admin/users/{user}/edit', [AdminUserController::class, 'edit'])->middleware('permission:users.manage')->name('admin.users.edit');
    Route::patch('/admin/users/{user}', [AdminUserController::class, 'update'])->middleware('permission:users.manage')->name('admin.users.update');
    Route::get('/admin/audit', [AuditTrailController::class, 'index'])->middleware('permission:audit.view')->name('admin.audit');
    Route::get('/admin/mail-settings', [MailSettingsController::class, 'edit'])->middleware('permission:admin.manage')->name('admin.mail-settings.edit');
    Route::patch('/admin/mail-settings', [MailSettingsController::class, 'update'])->middleware('permission:admin.manage')->name('admin.mail-settings.update');
    Route::get('/apps/datasets', [DatasetReportController::class, 'index'])->middleware('permission:admin.manage')->name('datasets.index');
    Route::post('/apps/datasets/reports', [DatasetReportController::class, 'store'])->middleware('permission:admin.manage')->name('datasets.reports.store');

    Route::middleware('permission:api.use')->group(function () {
        Route::get('/integrations/odoo', [OdooIntegrationController::class, 'index'])->name('odoo.index');
        Route::post('/integrations/odoo', [OdooIntegrationController::class, 'save'])->name('odoo.save');
        Route::post('/integrations/odoo/test', [OdooIntegrationController::class, 'test'])->name('odoo.test');
        Route::post('/integrations/odoo/imports', [OdooIntegrationController::class, 'importCsv'])->name('odoo.imports.store');
        Route::get('/integrations/odoo/imports/{batch}', [OdooIntegrationController::class, 'showImport'])->name('odoo.imports.show');
        Route::post('/integrations/odoo/sync', [OdooIntegrationController::class, 'sync'])->name('odoo.sync');
    });

    Route::get('/apps/{slug}', [AppModuleController::class, 'show'])->name('apps.show');
});
