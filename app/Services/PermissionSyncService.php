<?php

namespace App\Services;

use App\Models\User;
use App\Models\School;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Permission Sync Service
 * 
 * Handles automatic permission and role assignment for tenant users.
 * Ensures proper RBAC setup when users are created or roles change.
 */
class PermissionSyncService
{
    /**
     * Assign default role to a user based on school context
     */
    public function assignDefaultRole(User $user, School $school): void
    {
        // Set tenant context
        app()->instance('tenant', $school);

        // Determine role based on user email or position
        $roleName = $this->determineDefaultRole($user, $school);

        $role = Role::where('school_id', $school->id)
            ->where('name', $roleName)
            ->first();

        if ($role) {
            $user->assignRole($role);
        }
    }

    /**
     * Determine default role for a new user
     */
    protected function determineDefaultRole(User $user, School $school): string
    {
        // Check if this is the first user for the school
        $userCount = User::where('school_id', $school->id)->count();

        if ($userCount === 1) {
            return 'school_admin'; // First user is school admin
        }

        // Check email pattern for admin users
        if (str_contains($user->email, 'admin@')) {
            return 'school_admin';
        }

        // Check email pattern for teachers
        if (str_contains($user->email, 'teacher@')) {
            return 'teacher';
        }

        // Check email pattern for accountants
        if (str_contains($user->email, 'accountant@') || str_contains($user->email, 'finance@')) {
            return 'accountant';
        }

        // Default role
        return 'staff';
    }

    /**
     * Sync permissions when a user's role changes
     */
    public function syncPermissionsForRoleChange(User $user, string $oldRole, string $newRole): void
    {
        $school = $user->school;

        if (!$school) {
            return;
        }

        app()->instance('tenant', $school);

        // Remove old role
        $oldRoleObj = Role::where('school_id', $school->id)
            ->where('name', $oldRole)
            ->first();

        if ($oldRoleObj) {
            $user->removeRole($oldRoleObj);
        }

        // Assign new role
        $newRoleObj = Role::where('school_id', $school->id)
            ->where('name', $newRole)
            ->first();

        if ($newRoleObj) {
            $user->assignRole($newRoleObj);
        }
    }

    /**
     * Grant permission to multiple users in a school
     */
    public function grantPermissionToUsers(School $school, string $permissionName, array $userIds): void
    {
        app()->instance('tenant', $school);

        $permission = Permission::where('school_id', $school->id)
            ->where('name', $permissionName)
            ->first();

        if (!$permission) {
            // Create permission if it doesn't exist
            $permission = Permission::create([
                'school_id' => $school->id,
                'name' => $permissionName,
                'guard_name' => 'web',
                'group' => 'custom',
            ]);
        }

        foreach ($userIds as $userId) {
            $user = User::where('school_id', $school->id)->find($userId);
            if ($user) {
                $user->givePermissionTo($permission);
            }
        }
    }

    /**
     * Revoke permission from users
     */
    public function revokePermissionFromUsers(School $school, string $permissionName, array $userIds): void
    {
        app()->instance('tenant', $school);

        $permission = Permission::where('school_id', $school->id)
            ->where('name', $permissionName)
            ->first();

        if (!$permission) {
            return;
        }

        foreach ($userIds as $userId) {
            $user = User::where('school_id', $school->id)->find($userId);
            if ($user) {
                $user->revokePermissionTo($permission);
            }
        }
    }

    /**
     * Check if user can perform action on resource
     */
    public function can(User $user, string $permission, $resource = null): bool
    {
        // System admin bypasses all checks
        if ($user->role === 'system_admin') {
            return true;
        }

        // Check Spatie permission
        if ($user->hasPermissionTo($permission)) {
            return true;
        }

        // Resource-level check (if resource provided)
        if ($resource && method_exists($resource, 'school_id')) {
            return $resource->school_id === $user->school_id;
        }

        return false;
    }
}
