<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Subscription Model
 * 
 * Tracks subscription lifecycle for each school.
 * Works alongside School model for billing/trial management.
 * 
 * Status Flow: trial → active → expired/cancelled
 */
class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'plan_type', // trial, basic, premium, enterprise
        'status', // trial, active, expired, cancelled, past_due
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'stripe_subscription_id',
        'stripe_customer_id',
        'mpesa_checkout_id',
        'amount_paid',
        'currency',
        'payment_method', // stripe, mpesa, manual
        'auto_renew',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'amount_paid' => 'decimal:2',
        'auto_renew' => 'boolean',
    ];

    protected const PLANS = [
        'trial' => [
            'name' => '14-Day Trial',
            'price' => 0,
            'currency' => 'KES',
            'student_limit' => 100,
            'features' => ['basic_attendance', 'basic_grades', '5_staff_accounts'],
        ],
        'basic' => [
            'name' => 'Basic',
            'price' => 2999,
            'currency' => 'KES',
            'interval' => 'monthly',
            'student_limit' => 500,
            'features' => ['full_attendance', 'grades', 'reports', 'unlimited_staff'],
        ],
        'premium' => [
            'name' => 'Premium',
            'price' => 7999,
            'currency' => 'KES',
            'interval' => 'monthly',
            'student_limit' => 2000,
            'features' => ['attendance', 'grades', 'reports', 'sms_notifications', 'parent_portal'],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'price' => 19999,
            'currency' => 'KES',
            'interval' => 'monthly',
            'student_limit' => null, // unlimited
            'features' => ['everything', 'custom_reports', 'api_access', 'priority_support'],
        ],
    ];

    /**
     * School relationship
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Payment records
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Invoice records
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Check if in trial period
     */
    public function isTrial(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if expired (past end date)
     */
    public function isExpired(): bool
    {
        if ($this->status === 'cancelled') {
            return true;
        }

        if ($this->isTrial()) {
            return false;
        }

        return !$this->ends_at || $this->ends_at->isPast();
    }

    /**
     * Check if past due (payment failed)
     */
    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    /**
     * Get current plan details
     */
    public function getPlanDetails(): ?array
    {
        return self::PLANS[$this->plan_type] ?? null;
    }

    /**
     * Get student limit for current plan
     */
    public function getStudentLimit(): ?int
    {
        $plan = $this->getPlanDetails();
        return $plan['student_limit'] ?? null;
    }

    /**
     * Check if student limit exceeded
     */
    public function isStudentLimitExceeded(): bool
    {
        $limit = $this->getStudentLimit();
        if ($limit === null) {
            return false; // Unlimited
        }

        return $this->school->students()->count() > $limit;
    }

    /**
     * Get features for current plan
     */
    public function getFeatures(): array
    {
        $plan = $this->getPlanDetails();
        return $plan['features'] ?? [];
    }

    /**
     * Check if feature is available in current plan
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->getFeatures());
    }

    /**
     * Calculate days remaining until expiry
     */
    public function daysRemaining(): ?int
    {
        if ($this->isTrial() && $this->trial_ends_at) {
            return now()->diffInDays($this->trial_ends_at, false);
        }

        if ($this->ends_at) {
            return now()->diffInDays($this->ends_at, false);
        }

        return null;
    }

    /**
     * Cancel subscription
     */
    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'auto_renew' => false,
        ]);

        // Schedule end date if not already set
        if (!$this->ends_at) {
            $this->update(['ends_at' => now()]);
        }

        // TODO: Notify Stripe/M-Pesa to stop recurring charges
    }

    /**
     * Reactivate cancelled subscription
     */
    public function reactivate(): bool
    {
        if (!$this->stripe_subscription_id && !$this->mpesa_checkout_id) {
            return false; // Need payment method
        }

        $this->update([
            'status' => 'active',
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);

        return true;
    }

    /**
     * Extend trial (admin function)
     */
    public function extendTrial(int $days): bool
    {
        if ($this->status !== 'trial') {
            return false;
        }

        $this->trial_ends_at = $this->trial_ends_at->addDays($days);
        $this->save();

        return true;
    }

    /**
     * Mark as past due (payment failed)
     */
    public function markAsPastDue(): void
    {
        $this->update(['status' => 'past_due']);
    }

    /**
     * Restore from past due
     */
    public function restoreFromPastDue(): void
    {
        if ($this->ends_at && $this->ends_at->isFuture()) {
            $this->update(['status' => 'active']);
        } else {
            $this->update(['status' => 'expired']);
        }
    }
}
