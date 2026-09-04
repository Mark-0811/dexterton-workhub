<?php

namespace App\Http\Middleware;

use App\Models\Space;
use App\Models\AppNotification;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'title' => $user->title,
                    'username' => $user->username,
                    'timezone' => $user->timezone,
                    'account_type' => $user->account_type,
                    'office' => $user->office?->name,
                    'preferences' => $user->preferences,
                    'app_access' => $user->app_access,
                    'roles' => $user->roles()->pluck('slug'),
                    'permissions' => $user->permissionSlugs(),
                ] : null,
            ],
            'sidebarSpaces' => fn () => $user && $user->hasPermission('projects.view')
                ? Space::whereIn('id', $user->visibleSpaceIds())
                    ->withCount('projects')
                    ->orderBy('department')
                    ->orderBy('name')
                    ->get(['id', 'name', 'department', 'color'])
                : [],
            'notifications' => fn () => $user
                ? AppNotification::where('user_id', $user->id)
                    ->whereNull('read_at')
                    ->latest()
                    ->limit(8)
                    ->get(['id', 'title', 'body', 'href', 'category', 'created_at'])
                : [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
