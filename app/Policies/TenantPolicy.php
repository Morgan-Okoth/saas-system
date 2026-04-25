<?php

namespace App\Policies;

use App\Models\User;
use App\Models\School;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * TenantPolicy - Secondary authorization layer
 * 
 * IMPORTANT: Policies are NOT sufficient for tenant isolation!
 * 
 * Why GlobalScope + Middleware > Policies alone:
 * 
 * 1. Query-Level vs Application-Level:
 *    - GlobalScope: Database query level (impossible to bypass via Eloquent)
 *    - Policy: Application level only (bypassed by raw queries, forgotten checks)
 * 
 * 2. Memory Safety:
 *    - GlobalScope: Always attached to model, cannot be forgotten
 *    - Policy: Must be manually checked in every controller method
 * 
 * 3. Performance:
 *    - GlobalScope: Single WHERE clause on ALL queries (index-optimized)
 *    - Policy: PHP-level check AFTER data retrieval (too late)
 * 
 * 4. Developer Error:
 *    - GlobalScope: Automatic protection (cannot forget what doesn't exist to remember)
 *    - Policy: Easy to forget ->Gate::allows() in controller
 * 
 * Policy use case: Role-based actions within a tenant
 * (e.g., only school_admin can delete students, not teachers)
 */
class TenantPolicy
{
    use HandlesAuthorization;

    /**
     * Pre-authorization check for system admins
     * Runs before any other policy method
     */
    public function before(User $user, string $ability): ?bool
    {
        // System admins bypass all tenant-level checks
        // (but still need TenantMiddleware for context)
        if ($user->role === 'system_admin') {
            return true;
        }

        return null; // Continue to specific checks
    }

    /**
     * Determine if user can view any models within their tenant
     */
    public function viewAny(User $user, School $school): bool
    {
        // User can view models if they belong to the same school
        return $user->school_id === $school->id;
    }

    /**
     * Determine if user can view the school
     */
    public function view(User $user, School $school): bool
    {
        return $user->school_id === $school->id;
    }

    /**
     * Determine if user can update the school
     */
    public function update(User $user, School $school): bool
    {
        // Only school_admin or system_admin can update school details
        return $user->school_id === $school->id 
            && in_array($user->role, ['school_admin', 'system_admin']);
    }

    /**
     * Determine if user can delete the school
     */
    public function delete(User $user, School $school): bool
    {
        // Only system_admin can delete schools (soft delete only)
        return $user->role === 'system_admin';
    }
}
