<?php

namespace App\Models;

/**
 * Legacy alias for backward compatibility
 * School IS the tenant in this system
 */
class Tenant extends School
{
    protected $table = 'schools';
}
