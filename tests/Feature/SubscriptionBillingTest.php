<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\School;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

/**
 * Subscription & Billing Test Suite
 * 
 * Tests subscription lifecycle, payment processing,
 * and billing workflows.
 */
class SubscriptionBillingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function new_school_gets_trial_subscription()
    {
        $school = School::factory()->create();

        $this->assertNotNull($school->subscription);
        $this->assertEquals('trial', $school->subscription->status);
        $this->assertEquals('trial', $school->subscription->plan_type);
        $this->assertNotNull($school->subscription->trial_ends_at);
        $this->assertTrue($school->subscription->trial_ends_at->isFuture());
    }

    /** @test */
    public function trial_subscription_is_active_during_trial_period()
    {
        $school = School::factory()->create();
        $subscription = $school->subscription;

        $this->assertTrue($subscription->isTrial());
        $this->assertFalse($subscription->isExpired());
        $this->assertFalse($subscription->isActive()); // Still in trial, not "active" plan
    }

    /** @test */
    public function subscription_expires_after_trial_ends()
    {
        $school = School::factory()->create();
        $subscription = $school->subscription;

        // Fast-forward past trial
        $subscription->update([
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->assertTrue($subscription->isExpired());
        $this->assertFalse($subscription->isTrial());
    }

    /** @test */
    public function payment_upgrades_subscription_to_active()
    {
        $school = School::factory()->create();
        $subscription = $school->subscription;

        // Process payment
        $payment = new Payment();
        $payment->school()->associate($school);
        $payment->subscription()->associate($subscription);
        $payment->amount = 2999;
        $payment->currency = 'KES';
        $payment->provider = 'mpesa';
        $payment->status = 'completed';
        $payment->paid_at = now();
        $payment->save();

        // Subscription should be active
        $this->assertEquals('active', $subscription->fresh()->status);
        $this->assertEquals(2999, $subscription->fresh()->amount_paid);
    }

    /** @test */
    public function subscription_extends_on_recurring_payment()
    {
        $school = School::factory()->create();
        $subscription = $school->subscription;

        // Upgrade to active
        $subscription->update([
            'status' => 'active',
            'plan_type' => 'basic',
            'amount_paid' => 2999,
            'ends_at' => now()->addMonth(),
        ]);

        $originalEndsAt = $subscription->ends_at->copy();

        // Another payment (renewal)
        $payment = new Payment();
        $payment->school()->associate($school);
        $payment->subscription()->associate($subscription);
        $payment->amount = 2999;
        $payment->currency = 'KES';
        $payment->provider = 'mpesa';
        $payment->status = 'completed';
        $payment->paid_at = now();
        $payment->save();

        // Subscription should extend
        $this->assertTrue($subscription->fresh()->ends_at->gt($originalEndsAt));
    }

    /** @test */
    public function invoice_created_for_payment()
    {
        $school = School::factory()->create();
        $subscription = $school->subscription;

        $invoice = new Invoice();
        $invoice->school()->associate($school);
        $invoice->subscription()->associate($subscription);
        $invoice->invoice_number = Invoice::generateInvoiceNumber();
        $invoice->amount = 2999;
        $invoice->currency = 'KES';
        $invoice->status = 'paid';
        $invoice->due_date = now()->addDays(30);
        $invoice->paid_at = now();
        $invoice->line_items = [
            ['description' => 'Basic Plan', 'amount' => 2999, 'quantity' => 1],
        ];
        $invoice->save();

        $this->assertCount(1, $school->invoices);
        $this->assertEquals(2999, $school->invoices->first()->amount);
    }

    /** @test */
    public function subscription_cancellation_sets_cancelled_status()
    {
        $school = School::factory()->create();
        $subscription = $school->subscription;

        $subscription->update(['status' => 'active']);

        $subscription->cancel('No longer needed');

        $this->assertEquals('cancelled', $subscription->fresh()->status);
        $this->assertNotNull($subscription->fresh()->cancelled_at);
        $this->assertEquals('No longer needed', $subscription->fresh()->cancellation_reason);
    }

    /** @test */
    public function invoice_balance_calculation()
    {
        $school = School::factory()->create();

        $invoice = new Invoice();
        $invoice->school()->associate($school);
        $invoice->invoice_number = Invoice::generateInvoiceNumber();
        $invoice->amount = 5000;
        $invoice->currency = 'KES';
        $invoice->status = 'pending';
        $invoice->save();

        // Partial payment
        $payment = new Payment();
        $payment->school()->associate($school);
        $payment->invoice()->associate($invoice);
        $payment->amount = 2000;
        $payment->currency = 'KES';
        $payment->provider = 'mpesa';
        $payment->status = 'completed';
        $payment->paid_at = now();
        $payment->save();

        $this->assertEquals(3000, $invoice->fresh()->getBalance());
    }

    /** @test */
    public function plan_features_are_available()
    {
        $school = School::factory()->create();
        $subscription = $school->subscription;

        $subscription->update(['plan_type' => 'premium']);

        $this->assertTrue($subscription->hasFeature('sms_notifications'));
        $this->assertFalse($subscription->hasFeature('api_access'));
    }
}
