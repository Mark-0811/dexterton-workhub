<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Employees/Index', [
            'employees' => Employee::with(['user', 'manager', 'office'])->latest()->get(),
            'usersWithoutEmployee' => User::whereDoesntHave('roles', fn ($query) => $query->where('slug', 'guest'))->orderBy('name')->get(),
        ]);
    }
}
