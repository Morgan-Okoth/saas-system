<?php

namespace App\Services;

use App\Models\School;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\Invoice;
use Carbon\Carbon;

/**
 * Subscription Service
 * 
 * Manages subscription lifecycle, billing, and payment processing.
 * Integrates with Stripe and M-Pesa payment providers.
 */
class SubscriptionService
{
    /**
     * Create trial subscription for a new school
     */
    public function createTrialSubscription(School $school): Subscription
    {
        return $school->subscription()->create([
            'plan_type' => 'trial',
            'status' => 'trial',
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(14),
            'currency' => 'KES',
            'amount_paid' => 0,
            'auto_renew' => true,
        ]);
    }

    /**
     * Upgrade subscription to a paid plan
     */
    public function upgradeSubscription(School $school, string $planType, string $paymentMethod = 'stripe'): Subscription
    {
        $plans = config('subscriptions.plans', []);

        if (!isset($plans[$planType])) {
            throw new \InvalidArgumentException("Invalid plan type: {$planType}");
        }

        $plan = $plans[$planType];
        $subscription = $school->subscription;

        if (!$subscription) {
            $subscription = new Subscription();
            $subscription->school()->associate($school);
        }

        $subscription->fill([
            'plan_type' => $planType,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'currency' => $plan['currency'],
            'amount_paid' => $plan['price'],
            'payment_method' => $paymentMethod,
            'auto_renew' => true,
        ]);

        $subscription->save();

        // Create invoice
        $this->createInvoice($subscription, $plan['price']);

        return $subscription;
    }

    /**
     * Process payment from provider
     */
    public function processPayment(School $school, array $paymentData): Payment
    {
        $subscription = $school->subscription;

        $payment = new Payment();
        $payment->school()->associate($school);
        $payment->subscription()->associate($subscription);
        $payment->fill($paymentData);
        $payment->status = 'completed';
        $payment->paid_at = now();
        $payment->save();

        // Update subscription
        if ($subscription) {
            $this->extendSubscription($subscription, $payment->amount);
        }

        // Create or update invoice
        $this->recordPaymentOnInvoice($payment);

        return $payment;
    }

    /**
     * Extend subscription period
     */
    protected function extendSubscription(Subscription $subscription, float $amount): void
    {
        $endsAt = $subscription->ends_at && $subscription->ends_at->isFuture()
            ? $subscription->ends_at
            : now();

        $subscription->update([
            'status' => 'active',
            'amount_paid' => $subscription->amount_paid + $amount,
            'ends_at' => $endsAt->addMonth(),
        ]);
    }

    /**
     * Create invoice for subscription charge
     */
    protected function createInvoice(Subscription $subscription, float $amount): Invoice
    {
        return $subscription->invoices()->create([
            'school_id' => $subscription->school_id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'amount' => $amount,
            'currency' => $subscription->currency,
            'status' => 'paid',
            'due_date' => now()->addDays(30),
            'paid_at' => now(),
            'line_items' => [
                [
                    'description' => ucfirst($subscription->plan_type) . ' Plan Subscription',
                    'amount' => $amount,
                    'quantity' => 1,
                ],
            ],
        ]);
    }

    /**
     * Record payment on existing or new invoice
     */
    protected function recordPaymentOnInvoice(Payment $payment): void
    {
        $subscription = $payment->subscription;

        if (!$subscription) {
            return;
        }

        // Find pending invoice or create new one
        $invoice = $subscription->invoices()
            ->where('status', 'pending')
            ->first();

        if (!$invoice) {
            $invoice = $this->createInvoice($subscription, $payment->amount);
        } else {
            // Apply payment to existing invoice
            $invoice->payments()->save($payment);

            if ($invoice->getBalance() <= 0) {
                $invoice->markAsPaid();
            }
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(School $school, string $reason = null): void
    {
        $subscription = $school->subscription;

        if ($subscription) {
            $subscription->cancel($reason);
        }

        // TODO: Notify Stripe/M-Pesa to stop recurring billing
        // TODO: Send cancellation email to school admin
    }

    /**
     * Check and update expired subscriptions
     */
    public function checkExpiredSubscriptions(): void
    {
        $expired = Subscription::where('status', 'active')
            ->where('ends_at', '<', now())
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update(['status' => 'expired']);

            // Notify school admin
            $this->notifyExpiration($subscription->school);
        }
    }

    /**
     * Notify school of subscription expiration
     */
    protected function notifyExpiration(School $school): void
    {
        // TODO: Send expiration notification via Resend
    }

    /**
     * Get available subscription plans
     */
    public function getAvailablePlans(): array
    {
        return Subscription::PLANS;
    }
}
