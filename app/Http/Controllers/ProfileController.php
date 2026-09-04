<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Space;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'profile' => $request->user()->only(['name', 'username', 'email', 'title', 'timezone', 'preferences']),
            'accessSummary' => [
                'apps' => $request->user()->app_access ?: [],
                'roles' => $request->user()->roles()->orderBy('name')->pluck('name'),
                'permissions' => $request->user()->permissionSlugs(),
                'spaces' => Space::whereIn('id', $request->user()->visibleSpaceIds())
                    ->orderBy('department')
                    ->orderBy('name')
                    ->get(['id', 'department', 'name', 'color']),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $before = $user->only(['name', 'username', 'title', 'timezone', 'preferences']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username,'.$user->id.',id'],
            'title' => ['nullable', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:80'],
            'preferences' => ['required', 'array'],
            'preferences.theme' => ['required', 'in:light,dark'],
            'preferences.email_deadline_alerts' => ['required', 'boolean'],
            'preferences.in_app_notifications' => ['required', 'boolean'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $password = $data['password'] ?? null;
        unset($data['current_password'], $data['password'], $data['password_confirmation']);

        if ($password) {
            $data['password'] = Hash::make($password);
        }

        $user->update($data);

        AuditEvent::create([
            'actor_id' => $user->id,
            'subject_type' => $user::class,
            'subject_id' => $user->id,
            'event' => 'profile.updated',
            'before' => $before,
            'after' => $user->only(['name', 'username', 'title', 'timezone', 'preferences']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Profile settings saved.');
    }
}
