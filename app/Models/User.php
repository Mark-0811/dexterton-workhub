<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'organization_id', 'office_id', 'manager_id', 'name', 'username', 'email', 'title',
        'account_type', 'status', 'password', 'timezone', 'two_factor_enabled',
        'two_factor_secret', 'two_factor_recovery_codes', 'preferences', 'app_access',
        'last_login_at', 'invitation_token', 'invited_at',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'two_factor_recovery_codes' => 'encrypted:array',
            'preferences' => 'array',
            'app_access' => 'array',
            'last_login_at' => 'datetime',
            'invited_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withPivot('membership_role')->withTimestamps();
    }

    public function visibleSpaces(): BelongsToMany
    {
        return $this->belongsToMany(Space::class)->withPivot('access_level')->withTimestamps();
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->roles()->where('protected', true)->exists()) {
            return true;
        }

        if (in_array($slug, $this->appGrantedPermissions(), true)) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('slug', $slug))
            ->exists();
    }

    public function permissionSlugs(): array
    {
        if ($this->roles()->where('protected', true)->exists()) {
            return Permission::pluck('slug')->all();
        }

        return $this->roles()
            ->with('permissions:id,slug')
            ->get()
            ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
            ->merge($this->appGrantedPermissions())
            ->unique()
            ->values()
            ->all();
    }

    public function appGrantedPermissions(): array
    {
        $access = $this->app_access;
        if (! is_array($access) || $access === []) {
            return [];
        }

        $map = [
            'requests' => ['requests.create'],
            'projects' => ['projects.view', 'projects.create'],
            'todos' => ['todos.manage'],
            'workflows' => ['workflows.manage'],
            'odoo' => ['api.use'],
            'audit' => ['audit.view'],
            'admin' => ['admin.manage'],
            'employees' => ['users.manage'],
            'spaces' => ['projects.view'],
        ];

        return collect($access)
            ->flatMap(fn ($app) => $map[$app] ?? [])
            ->unique()
            ->values()
            ->all();
    }

    public function canSeeAllSpaces(): bool
    {
        return $this->hasPermission('projects.manage');
    }

    public function visibleSpaceIds(): array
    {
        if ($this->canSeeAllSpaces()) {
            return Space::where('active', true)->pluck('id')->all();
        }

        return $this->visibleSpaces()
            ->where('active', true)
            ->pluck('spaces.id')
            ->all();
    }
}
