<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $systemOwner = Role::where('slug', 'system-owner')->first();

        if ($systemOwner) {
            $systemOwner->forceFill(['protected' => true])->save();
            $systemOwner->permissions()->syncWithoutDetaching(Permission::pluck('id')->all());
        }

        User::whereHas('roles', fn ($query) => $query->where('slug', 'system-owner'))
            ->get()
            ->each(function (User $user) {
                $user->forceFill([
                    'status' => 'active',
                    'app_access' => [
                        'dashboard',
                        'spaces',
                        'employees',
                        'requests',
                        'workflows',
                        'projects',
                        'todos',
                        'mails',
                        'odoo',
                        'audit',
                        'admin',
                        'service-flows',
                        'expenses',
                        'bookings',
                        'documents',
                        'e-signature',
                        'automations',
                        'webforms',
                        'datasets',
                    ],
                ])->save();
            });
    }

    public function down(): void
    {
        //
    }
};
