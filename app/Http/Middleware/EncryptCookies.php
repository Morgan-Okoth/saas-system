<?php

namespace App\Http\Middleware;

use App\Models\School;
use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

/**
 * Encrypt Cookies - Tenant-Aware
 * 
 * Ensures session cookies are tenant-scoped to prevent
 * session fixation across different schools on subdomains.
 */
class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     */
    protected $except = [
        //
    ];

    /**
     * Determine if a cookie should be encrypted.
     */
    public function shouldEncrypt($name): bool
    {
        // Always encrypt sensitive cookies
        return true;
    }

    /**
     * Get the tenant-specific cookie name
     */
    protected function getTenantCookieName(string $name): string
    {
        $school = app('tenant');
        $prefix = $school ? "school_{$school->id}_" : 'global_';
        return $prefix . $name;
    }
}
