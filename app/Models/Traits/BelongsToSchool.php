<?php

namespace App\Models\Traits;

use Illuminate\Support\Facades\Auth;

/**
 * Trait BelongsToSchool
 * 
 * Automatically injects school_id on model creation.
 * This ensures NO tenant-owned model can be created without a school_id.
 * 
 * CRITICAL: This trait MUST be used on every tenant-owned model.
 */
trait BelongsToSchool
{
    /**
     * Boot the trait - register creating event
     */
    protected static function bootBelongsToSchool(): void
    {
        static::creating(function ($model) {
            // If school_id is already set (e.g., explicitly passed), respect it
            if (empty($model->school_id)) {
                // Get current tenant from application context
                $school = app('tenant');

                if ($school) {
                    $model->school_id = $school->id;
                } else {
                    // NO tenant context means this is a system-level creation
                    // Only allow if explicitly permitted (e.g., system_admin creating first school)
                    if (! static::allowCreationWithoutTenant()) {
                        throw new \RuntimeException(
                            'Cannot create tenant-owned model without a school_id. '
                            . 'Tenant context is missing.'
                        );
                    }
                }
            }
        });

        // Optional: Also scope queries automatically via GlobalScope
        // static::addGlobalScope(new \App\Models\Scopes\TenantScope());
    }

    /**
     * Determine if model can be created without tenant.
     * Override in models that allow system-level creation.
     */
    protected static function allowCreationWithoutTenant(): bool
    {
        return false;
    }
}
