<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Support\Facades\Log;

/**
 * Verify CSRF Token - Production Hardened
 * 
 * CSRF protection for all state-changing requests.
 * Critical for multi-tenant SaaS security.
 */
class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     * 
     * Exclude webhook endpoints (Stripe/M-Pesa callbacks)
     */
    protected $except = [
        'stripe/*',
        'webhook/mpesa/*',
        'api/*',
    ];

    /**
     * Handle CSRF token mismatch - Production logging
     */
    public function handle($request, \Closure $next)
    {
        try {
            return parent::handle($request, $next);
        } catch (\Illuminate\Session\TokenMismatchException $e) {
            // Log CSRF failures for security monitoring
            Log::warning('CSRF token mismatch', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'tenant_id' => app('tenant')?->id ?? 'none',
                'user_id' => auth()->id() ?? 'guest',
            ]);

            throw $e;
        }
    }

    /**
     * Determine if token should be verified for this request
     */
    protected function shouldPassThrough($request): bool
    {
        // Skip CSRF for webhook endpoints
        if ($request->is('stripe/*') || $request->is('webhook/mpesa/*')) {
            return true;
        }

        return parent::shouldPassThrough($request);
    }
}
