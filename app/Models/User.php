<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'school_id',
        'name',
        'email',
        'password',
        'role', // system_admin, school_admin, teacher, staff
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * CRITICAL: Every user belongs to a school (except system super-admins)
     * This is the tenant boundary enforcement
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Tenant-aware: students managed by this user
     */
    public function students()
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Security: Check if user can access given tenant
     */
    public function belongsToTenant(int $schoolId): bool
    {
        // System super-admins bypass tenant checks (handled by middleware)
        if ($this->role === 'system_admin') {
            return true;
        }

        return $this->school_id === $schoolId;
    }
}
