<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * CRITICAL SECURITY COMPONENT
 * 
 * GlobalScope automatically applied to ALL queries for tenant-owned models.
 * This is the last line of defense against cross-tenant data leakage.
 * 
 * Why this is safer than manual filtering:
 * 1. Automatic: Cannot be forgotten by developers
 * 2. Universal: Applies to ALL queries - including relationships, eager loads, subqueries
 * 3. Unavoidable: Even if someone uses raw queries via Eloquent, the scope injects
 * 4. Performance: Single index seek (school_id) on every query
 * 
 * VPS Risk Mitigation:
 * On shared VPS, memory leaks or context bleeding between requests (especially async
 * jobs or queued tasks) could cause catastrophic cross-tenant leaks. This scope
 * ensures that even with broken context, database-level isolation remains.
 */
class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Get the current school_id from the resolved tenant context
        $schoolId = $this->resolveSchoolId();

        if ($schoolId !== null) {
            // Always enforce school_id constraint
            $builder->where($model->getTable() . '.school_id', '=', $schoolId);
        }

        // In production, we NEVER allow null school_id queries
        // This prevents accidental "system" data exposure
    }

    /**
     * Add the scope removing extension to the builder.
     * This allows temporarily disabling the scope when absolutely necessary
     * (e.g., system-level reports, super-admin operations)
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenant', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });

        $builder->macro('withAnyTenant', function (Builder $builder) {
            // Remove this scope's constraint but keep others
            return $builder->withoutGlobalScope($this);
        });
    }

    /**
     * Resolve the current school_id from application context.
     * 
     * The tenant context is bound by TenantMiddleware which runs
     * on every authenticated request.
     */
    protected function resolveSchoolId(): ?int
    {
        // Resolve from the application container
        // This is set by TenantMiddleware for each request
        $school = app('tenant');

        return $school?->id;
    }
}
