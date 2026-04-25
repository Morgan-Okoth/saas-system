<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\School;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Auth Service Provider
 * 
 * Registers all authorization gates and policies.
 * 
 * IMPORTANT: Gates run AFTER global scope filtering.
 * They provide role-based access control WITHIN tenant context.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Policy mappings (none needed - using Spatie)
     */
    protected $policies = [
        // Model => Policy::class
    ];

    /**
     * Register authentication services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerGates();
    }

    /**
     * Define authorization gates
     */
    protected function registerGates(): void
    {
        // Gate: Check if user has specific permission
        Gate::define('has-permission', function (User $user, string $permission) {
            // System admin bypasses all permission checks
            if ($user->role === 'system_admin') {
                return true;
            }

            // Check Spatie permission (scoped to school via tenant context)
            return $user->hasPermissionTo($permission);
        });

        // Gate: Check if user has any of given permissions
        Gate::define('has-any-permission', function (User $user, array $permissions) {
            if ($user->role === 'system_admin') {
                return true;
            }

            foreach ($permissions as $permission) {
                if ($user->hasPermissionTo($permission)) {
                    return true;
                }
            }
            return false;
        });

        // Gate: Check if user owns the tenant resource
        Gate::define('owns-tenant', function (User $user, School $school) {
            return $user->school_id === $school->id;
        });

        // Gate: Role-based access
        Gate::define('has-role', function (User $user, string $role) {
            if ($user->role === 'system_admin') {
                return true;
            }
            return $user->role === $role;
        });

        // Gate: Any of multiple roles
        Gate::define('has-any-role', function (User $user, array $roles) {
            if ($user->role === 'system_admin') {
                return true;
            }
            return in_array($user->role, $roles);
        });

        // Gate: School admin or higher
        Gate::define('admin-or-higher', function (User $user) {
            return in_array($user->role, ['system_admin', 'school_admin']);
        });

        // Gate: Can manage users
        Gate::define('manage-users', function (User $user) {
            return $user->hasPermissionTo('create users') ||
                   $user->hasPermissionTo('edit users') ||
                   $user->hasPermissionTo('assign roles');
        });

        // Gate: Can manage billing
        Gate::define('manage-billing', function (User $user) {
            return $user->hasPermissionTo('update subscription') ||
                   $user->hasPermissionTo('refund payments');
        });
    }
}
