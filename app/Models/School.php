<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Carbon;

class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'county',
        'subscription_status',
        'trial_ends_at',
        'subscription_ends_at',
        'settings',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'settings' => 'array',
        'deleted_at' => 'datetime',
    ];

    protected const SUBSCRIPTION_TRIAL = 'trial';
    protected const SUBSCRIPTION_ACTIVE = 'active';
    protected const SUBSCRIPTION_EXPIRED = 'expired';
    protected const SUBSCRIPTION_CANCELLED = 'cancelled';

    /**
     * All tenant-owned models are scoped to school_id
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function teachers()
    {
        return $this->hasMany(Teacher::class);
    }

    /**
     * Critical: Determine if school can access system features
     */
    public function isActive(): bool
    {
        return $this->subscription_status === self::SUBSCRIPTION_ACTIVE;
    }

    public function isTrial(): bool
    {
        return $this->subscription_status === self::SUBSCRIPTION_TRIAL;
    }

    public function isExpired(): bool
    {
        $status = $this->subscription_status;
        $now = Carbon::now();

        if ($status === self::SUBSCRIPTION_EXPIRED) {
            return true;
        }

        // Trial expired
        if ($status === self::SUBSCRIPTION_TRIAL && $this->trial_ends_at && $this->trial_ends_at->isPast()) {
            return true;
        }

        // Subscription expired
        if ($this->subscription_ends_at && $this->subscription_ends_at->isPast()) {
            return true;
        }

        return false;
    }

    /**
     * VPS Performance: Cache frequently accessed tenant config
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }
}
