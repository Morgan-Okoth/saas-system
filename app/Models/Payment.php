<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Payment Model
 * 
 * Records individual payment transactions.
 * Supports multiple providers: Stripe, M-Pesa, manual.
 */
class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'subscription_id',
        'invoice_id',
        'amount',
        'currency',
        'provider', // stripe, mpesa, manual, bank_transfer
        'provider_transaction_id',
        'status', // pending, completed, failed, refunded
        'paid_at',
        'refunded_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /**
     * School relationship
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Subscription relationship
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Invoice relationship
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Check if payment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if payment failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if payment was refunded
     */
    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    /**
     * Mark payment as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        // Update subscription
        if ($this->subscription) {
            $this->updateSubscriptionStatus();
        }
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);

        // Notify school admin
        if ($this->subscription && $this->subscription->status === 'active') {
            $this->subscription->markAsPastDue();
        }
    }

    /**
     * Mark payment as refunded
     */
    public function markAsRefunded(): void
    {
        $this->update([
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);
    }

    /**
     * Update subscription based on payment
     */
    protected function updateSubscriptionStatus(): void
    {
        $subscription = $this->subscription;

        if (!$subscription) {
            return;
        }

        switch ($subscription->status) {
            case 'trial':
                // Trial ended, activate subscription
                $subscription->update([
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => now()->addMonth(), // or based on plan
                ]);
                break;

            case 'past_due':
                // Restore from past due
                $subscription->restoreFromPastDue();
                break;

            case 'expired':
                // Reactivate
                $subscription->update([
                    'status' => 'active',
                    'ends_at' => now()->addMonth(),
                ]);
                break;

            case 'active':
                // Extend subscription period
                $endsAt = $subscription->ends_at && $subscription->ends_at->isFuture()
                    ? $subscription->ends_at
                    : now();

                $subscription->update([
                    'ends_at' => $endsAt->addMonth(),
                ]);
                break;
        }
    }
}
