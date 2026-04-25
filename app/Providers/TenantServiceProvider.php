<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Scopes\TenantScope;
use App\Models\School;

/**
 * TenantServiceProvider
 * 
 * Registers tenant-aware models and bootstraps global scopes.
 * 
 * The boot process ensures that ALL tenant-owned models have the TenantScope
 * applied automatically. This is registered after middleware runs.
 */
class TenantServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind a fallback tenant resolver
        $this->app->singleton('tenant', function ($app) {
            // Default null tenant for early boot or testing
            return $app->make('request')->attributes->get('tenant');
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Apply TenantScope to all tenant-owned models
        // This happens on every request after middleware has bound the tenant
        School::addGlobalScope(new TenantScope());
        // Note: School itself doesn't need TenantScope (it IS the tenant)
        // But Student, User, and all child models DO
    }
}
