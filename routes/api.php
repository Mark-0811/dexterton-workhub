<?php

use App\Http\Controllers\Api\V1\PlatformController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/', [PlatformController::class, 'catalog'])->name('catalog');
    Route::get('/users', [PlatformController::class, 'users'])->name('users');
    Route::get('/groups', [PlatformController::class, 'groups'])->name('groups');
    Route::get('/projects', [PlatformController::class, 'projects'])->name('projects');
    Route::get('/requests', [PlatformController::class, 'requests'])->name('requests');
    Route::get('/workflows', [PlatformController::class, 'workflows'])->name('workflows');
    Route::get('/todos', [PlatformController::class, 'todos'])->name('todos');
    Route::get('/audit', [PlatformController::class, 'audit'])->name('audit');
});
