<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\School;

/**
 * CRITICAL MIDDLEWARE
 * 
 * Binds the authenticated user's school (tenant) to the application container
 * for the duration of the request lifecycle.
 * 
 * How request lifecycle enforces tenancy:
 * 1. User authenticates (Laravel Breeze)
 * 2. Every subsequent request includes session/auth
 * 3. This middleware runs on every request (except auth/login routes)
 * 4. Resolves the user's school_id -> School model
 * 5. Binds School to 'tenant' key in app container
 * 6. TenantScope reads from app('tenant') to apply school_id constraints
 * 7. All Eloquent queries automatically filter by school_id
 * 
 * What happens if tenant context is missing:
 * - Auth'd user with NULL school_id: 500 error (should not happen in production)
 * - No auth (guest): Middleware excluded from guest routes, no tenant binding
 * - Auth user school not found: 404/500 (tenant deleted but user remains)
 * 
 * VPS Security:
 * Without this, VPS shared resources (Redis cache, queue workers) could leak
 * data between tenants if context isn't properly isolated per request.
 */
class TenantMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only apply to authenticated users
        if (Auth::check()) {
            $user = Auth::user();

            // System super-admins bypass tenant scoping
            // They have access to all schools (handled by separate middleware)
            if ($user->role === 'system_admin') {
                // No tenant binding - queries without school_id constraint
                // Admin routes should explicitly handle multi-tenant access
                return $next($request);
            }

            // Validate that user belongs to a school
            if (is_null($user->school_id)) {
                // This is a critical data integrity issue
                // Log out the user and force re-authentication
                Auth::logout();
                return redirect('/login')->withErrors([
                    'email' => 'Your account is not associated with any school.',
                ]);
            }

            // Resolve the school model
            $school = School::withoutGlobalScopes()->find($user->school_id);

            if (is_null($school)) {
                // School has been deleted but user record remains
                // Force logout - tenant no longer exists
                Auth::logout();
                return redirect('/login')->withErrors([
                    'email' => 'Your school no longer exists.',
                ]);
            }

            // Bind to application container for this request lifecycle
            // TenantScope will read this via app('tenant')
            app()->instance('tenant', $school);

            // Also set as request attribute for easy access in controllers
            $request->attributes->set('tenant', $school);

            // Share with all views (Inertia/Blade) if needed
            // \Illuminate\Support\Facades\View::share('tenant', $school);
        }

        return $next($request);
    }
}
