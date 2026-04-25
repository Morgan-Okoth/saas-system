<?php

namespace App\Observers;

use App\Models\School;
use App\Models\Subscription;

/**
 * School Observer
 * 
 * Automatically manages subscription lifecycle when school is created/updated.
 */
class SchoolObserver
{
    /**
     * Handle School "created" event.
     */
    public function created(School $school): void
    {
        // Auto-create trial subscription
        $school->subscription()->create([
            'plan_type' => 'trial',
            'status' => 'trial',
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(14),
            'currency' => 'KES',
            'auto_renew' => true,
        ]);

        // TODO: Send welcome email via Resend
        // \Mail::to($school->email)->send(new SchoolWelcome($school));
    }

    /**
     * Handle School "updating" event.
     */
    public function updating(School $school): void
    {
        // If subscription_status is being updated, sync with subscription
        if ($school->isDirty('subscription_status')) {
            $this->syncSubscriptionStatus($school);
        }
    }

    /**
     * Sync school subscription_status with actual subscription
     */
    protected function syncSubscriptionStatus(School $school): void
    {
        $subscription = $school->subscription;

        if (!$subscription) {
            return;
        }

        $newStatus = $school->subscription_status;

        switch ($newStatus) {
            case 'active':
                $subscription->update([
                    'status' => 'active',
                    'ends_at' => $subscription->ends_at ?? now()->addMonth(),
                ]);
                break;

            case 'expired':
            case 'cancelled':
                $subscription->update([
                    'status' => $newStatus,
                    'cancelled_at' => now(),
                ]);
                break;
        }
    }
}
