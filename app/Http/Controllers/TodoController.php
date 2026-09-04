<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TodoController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Todos/Index', [
            'todos' => Todo::where('user_id', $request->user()->id)->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'due_at' => ['nullable', 'date'],
        ]);

        Todo::create($data + ['user_id' => $request->user()->id]);

        return back()->with('success', 'Todo added.');
    }

    public function toggle(Todo $todo): RedirectResponse
    {
        abort_unless($todo->user_id === request()->user()->id, 403);
        $todo->forceFill(['completed_at' => $todo->completed_at ? null : now()])->save();

        return back();
    }
}
