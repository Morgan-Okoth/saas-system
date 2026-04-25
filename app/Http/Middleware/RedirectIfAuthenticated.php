<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Redirect If Authenticated
 * 
 * Prevents authenticated users from accessing auth pages.
 * Respects tenant context - authenticated users always have
 * a school context after login.
 */
class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$guards): \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Closure
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // User is authenticated - redirect to tenant dashboard
                // TenantMiddleware will bind school context
                return redirect(RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}
