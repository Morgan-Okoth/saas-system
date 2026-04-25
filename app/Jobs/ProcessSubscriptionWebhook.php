<?php

namespace App\Jobs;

use App\Models\School;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Process Subscription Webhook Job
 * 
 * Handles webhook notifications from Stripe/M-Pesa.
 * Runs on queue to avoid request timeouts.
 * 
 * IMPORTANT: Explicitly sets tenant context since queue workers
 * may process jobs for multiple schools.
 */
class ProcessSubscriptionWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Timeout in seconds.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $schoolId,
        public array $webhookData,
        public string $provider
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SubscriptionService $subscriptionService): void
    {
        // Resolve school
        $school = School::find($this->schoolId);

        if (!$school) {
            \Log::error('School not found for webhook processing', [
                'school_id' => $this->schoolId,
                'provider' => $this->provider,
            ]);
            return;
        }

        // Set tenant context for this job
        app()->instance('tenant', $school);

        // Process webhook based on provider
        switch ($this->provider) {
            case 'stripe':
                $this->processStripeWebhook($school, $subscriptionService);
                break;

            case 'mpesa':
                $this->processMpesaWebhook($school, $subscriptionService);
                break;

            default:
                \Log::error('Unknown webhook provider', [
                    'provider' => $this->provider,
                    'school_id' => $school->id,
                ]);
        }
    }

    /**
     * Process Stripe webhook
     */
    protected function processStripeWebhook(School $school, SubscriptionService $service): void
    {
        $eventType = $this->webhookData['type'] ?? '';

        switch ($eventType) {
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdate($school, $service);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionCancellation($school, $service);
                break;

            case 'invoice.payment_succeeded':
                $this->handlePaymentSuccess($school, $service);
                break;

            case 'invoice.payment_failed':
                $this->handlePaymentFailure($school, $service);
                break;

            default:
                \Log::info('Unhandled Stripe webhook event', [
                    'event' => $eventType,
                    'school_id' => $school->id,
                ]);
        }
    }

    /**
     * Process M-Pesa webhook
     */
    protected function processMpesaWebhook(School $school, SubscriptionService $service): void
    {
        $resultCode = $this->webhookData['ResultCode'] ?? '';
        $resultDesc = $this->webhookData['ResultDesc'] ?? '';

        if ($resultCode === '0') {
            // Payment successful
            $this->handleMpesaSuccess($school, $service);
        } else {
            // Payment failed
            \Log::warning('M-Pesa payment failed', [
                'school_id' => $school->id,
                'code' => $resultCode,
                'description' => $resultDesc,
            ]);
        }
    }

    /**
     * Handle subscription update from Stripe
     */
    protected function handleSubscriptionUpdate(School $school, SubscriptionService $service): void
    {
        $subscriptionData = $this->webhookData['data']['object'] ?? [];
        $status = $subscriptionData['status'] ?? '';

        $subscription = $school->subscription;

        if (!$subscription) {
            return;
        }

        switch ($status) {
            case 'active':
                $subscription->update([
                    'status' => 'active',
                    'ends_at' => now()->addMonth(),
                ]);
                break;

            case 'past_due':
                $subscription->update(['status' => 'past_due']);
                break;

            case 'canceled':
                $subscription->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);
                break;
        }
    }

    /**
     * Handle subscription cancellation
     */
    protected function handleSubscriptionCancellation(School $school, SubscriptionService $service): void
    {
        $subscription = $school->subscription;

        if ($subscription) {
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        }
    }

    /**
     * Handle successful payment
     */
    protected function handlePaymentSuccess(School $school, SubscriptionService $service): void
    {
        $invoiceData = $this->webhookData['data']['object'] ?? [];
        $amount = ($invoiceData['amount_paid'] ?? 0) / 100; // Convert cents to KES

        // Create payment record
        $school->payments()->create([
            'amount' => $amount,
            'currency' => 'KES',
            'provider' => 'stripe',
            'provider_transaction_id' => $invoiceData['id'] ?? '',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        // Extend subscription
        $subscription = $school->subscription;
        if ($subscription) {
            $subscription->update([
                'status' => 'active',
                'ends_at' => now()->addMonth(),
                'amount_paid' => $subscription->amount_paid + $amount,
            ]);
        }
    }

    /**
     * Handle payment failure
     */
    protected function handlePaymentFailure(School $school, SubscriptionService $service): void
    {
        $subscription = $school->subscription;

        if ($subscription && $subscription->status === 'active') {
            $subscription->update(['status' => 'past_due']);
        }

        // Notify school admin
        // TODO: Send email notification via Resend
    }

    /**
     * Handle M-Pesa payment success
     */
    protected function handleMpesaSuccess(School $school, SubscriptionService $service): void
    {
        $amount = $this->webhookData['TransAmount'] ?? 0;
        $transactionId = $this->webhookData['TransID'] ?? '';

        // Create payment record
        $school->payments()->create([
            'amount' => $amount,
            'currency' => 'KES',
            'provider' => 'mpesa',
            'provider_transaction_id' => $transactionId,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        // Extend subscription
        $subscription = $school->subscription;
        if ($subscription) {
            $subscription->update([
                'status' => 'active',
                'ends_at' => now()->addMonth(),
                'amount_paid' => $subscription->amount_paid + $amount,
            ]);
        }
    }
}
