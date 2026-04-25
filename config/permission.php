<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Multi-Tenant Spatie Permission Configuration
    |--------------------------------------------------------------------------
    |
    | CRITICAL: Spatie permissions MUST be scoped to tenant to prevent
    | cross-tenant permission leakage.
    |
    | We use 'team_id' (here called 'school_id') to scope all permissions.
    |
    */

    'guard_name' => 'web',

    'team_foreign_key' => 'school_id',

    /*
    |--------------------------------------------------------------------------
    | Permission Models
    |--------------------------------------------------------------------------
    |
    | The permission model determines how permissions are stored.
    | Must be scoped to the current tenant (school_id).
    |
    */

    'models' => [
        'permission' => Spatie\Permission\Models\Permission::class,
        'role' => Spatie\Permission\Models\Role::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | All Spatie tables MUST include school_id foreign key for tenant isolation.
    |
    */

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Column Names
    |--------------------------------------------------------------------------
    |
    */

    'column_names' => [
        'model_morph_key' => 'model_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache & Performance (VPS-Optimized)
    |--------------------------------------------------------------------------
    |
    | Redis cache for permission lookups - critical for performance.
    | Cache keys MUST include school_id to prevent cross-tenant leakage.
    |
    */

    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache.tenant_' . (app('tenant')?->id ?? 'global'),
        'model' => \Spatie\Permission\Models\Permission::class,
        'store' => 'redis', // Use Redis on VPS
        'prefix' => 'spatie_permission_' . (app('tenant')?->id ?? 'global') . '_',
    ],
];
