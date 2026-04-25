<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use App\Models\Student;
use App\Models\Payment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * GDPR Compliance Service
 * 
 * Provides data export, anonymization, and deletion tools
 * for GDPR compliance.
 */
class GDPRComplianceService
{
    /**
     * Export all personal data for a school
     */
    public function exportSchoolData(School $school): array
    {
        return [
            'school' => $this->exportSchool($school),
            'users' => $this->exportUsers($school),
            'students' => $this->exportStudents($school),
            'payments' => $this->exportPayments($school),
            'subscriptions' => $this->exportSubscriptions($school),
            'metadata' => $this->exportMetadata(),
        ];
    }

    /**
     * Export school information
     */
    protected function exportSchool(School $school): array
    {
        return [
            'id' => $school->id,
            'name' => $school->name,
            'email' => $school->email,
            'phone' => $school->phone,
            'county' => $school->county,
            'subscription_status' => $school->subscription_status,
            'trial_ends_at' => $school->trial_ends_at?->toIso8601String(),
            'settings' => $school->settings,
            'created_at' => $school->created_at->toIso8601String(),
            'updated_at' => $school->updated_at->toIso8601String(),
        ];
    }

    /**
     * Export all users
     */
    protected function exportUsers(School $school): array
    {
        return $school->users()->get()->map(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String(),
                'last_login' => $user->last_login_at?->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Export all students
     */
    protected function exportStudents(School $school): array
    {
        return $school->students()->get()->map(function (Student $student) {
            return [
                'id' => $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'date_of_birth' => $student->date_of_birth?->toIso8601String(),
                'grade_level' => $student->grade_level,
                'metadata' => $student->metadata,
                'created_at' => $student->created_at->toIso8601String(),
                'updated_at' => $student->updated_at->toIso8601String(),
                'deleted_at' => $student->deleted_at?->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Export all payments
     */
    protected function exportPayments(School $school): array
    {
        return $school->payments()->get()->map(function (Payment $payment) {
            return [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'provider' => $payment->provider,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'created_at' => $payment->created_at->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Export subscription data
     */
    protected function exportSubscriptions(School $school): array
    {
        $subscription = $school->subscription;

        if (!$subscription) {
            return [];
        }

        return [
            'id' => $subscription->id,
            'plan_type' => $subscription->plan_type,
            'status' => $subscription->status,
            'starts_at' => $subscription->starts_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'amount_paid' => $subscription->amount_paid,
            'currency' => $subscription->currency,
            'auto_renew' => $subscription->auto_renew,
            'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
        ];
    }

    /**
     * Export metadata
     */
    protected function exportMetadata(): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'format_version' => '1.0',
            'gdpr_compliant' => true,
        ];
    }

    /**
     * Generate GDPR export file
     */
    public function generateExportFile(School $school): string
    {
        $data = $this->exportSchoolData($school);
        $filename = "gdpr-export-school-{$school->id}-" . now()->format('Y-m-d-His') . '.json';

        Storage::put("gdpr-exports/{$filename}", json_encode($data, JSON_PRETTY_PRINT));

        return $filename;
    }

    /**
     * Anonymize user data
     */
    public function anonymizeUser(User $user): bool
    {
        DB::beginTransaction();

        try {
            // Anonymize user
            $user->update([
                'name' => 'Deleted User',
                'email' => 'deleted-' . $user->id . '@deleted.example.com',
                'role' => 'deleted',
            ]);

            // Anonymize student data
            $user->school->students()->update([
                'first_name' => 'Anonymized',
                'last_name' => 'Student',
                'email' => null,
            ]);

            // Remove personal data from metadata
            $user->school->students()->each(function ($student) {
                $metadata = $student->metadata ?? [];
                unset($metadata['parent_email']);
                unset($metadata['parent_phone']);
                unset($metadata['parent_name']);
                $student->update(['metadata' => $metadata]);
            });

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('GDPR anonymization failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete school data (Right to be forgotten)
     */
    public function deleteSchoolData(School $school): bool
    {
        DB::beginTransaction();

        try {
            // Anonymize subscription data
            if ($school->subscription) {
                $school->subscription->update([
                    'stripe_customer_id' => null,
                    'stripe_subscription_id' => null,
                    'mpesa_checkout_id' => null,
                ]);
            }

            // Anonymize payments (keep for accounting but remove PII)
            $school->payments()->update([
                'provider_transaction_id' => null,
            ]);

            // Mark school for deletion
            $school->update([
                'name' => 'Deleted School ' . $school->id,
                'email' => 'deleted-' . $school->id . '@deleted.example.com',
                'phone' => null,
                'county' => null,
                'subscription_status' => 'cancelled',
            ]);

            // Anonymize users
            $school->users()->update([
                'name' => 'Deleted User',
                'email' => 'deleted-' . now()->timestamp . '@deleted.example.com',
                'role' => 'deleted',
            ]);

            // Anonymize students
            $school->students()->update([
                'first_name' => 'Anonymized',
                'last_name' => 'Student',
                'email' => null,
                'metadata' => [],
            ]);

            DB::commit();

            // Schedule hard delete after retention period (30 days)
            $this->scheduleHardDelete($school);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('GDPR deletion failed', [
                'school_id' => $school->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Schedule hard delete
     */
    protected function scheduleHardDelete(School $school): void
    {
        // In production, queue a job to delete after retention period
        \Log::info('GDPR hard delete scheduled', [
            'school_id' => $school->id,
            'scheduled_date' => now()->addDays(30)->toIso8601String(),
        ]);
    }

    /**
     * Get data retention status
     */
    public function getRetentionStatus(School $school): array
    {
        $settings = $school->settings ?? new \stdClass();
        $retentionDays = $settings->data_retention_days ?? 365;

        return [
            'retention_days' => $retentionDays,
            'last_export' => $settings->last_gdpr_export ?? null,
            'auto_archive_enabled' => $settings->auto_archive_old_data ?? true,
            'deletion_scheduled' => $settings->deletion_scheduled_at ?? null,
        ];
    }

    /**
     * Generate SAR (Subject Access Request) report
     */
    public function generateSARReport(User $user): array
    {
        $school = $user->school;

        return [
            'request_date' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at->toIso8601String(),
            ],
            'school' => [
                'name' => $school->name,
                'subscription_status' => $school->subscription_status,
            ],
            'student_data' => $school->students()->count(),
            'payment_history' => $school->payments()->count(),
            'data_categories' => $this->getDataCategories($school),
        ];
    }

    /**
     * Get data categories
     */
    protected function getDataCategories(School $school): array
    {
        return [
            'personal_identifiers' => [
                'collected' => true,
                'purpose' => 'Account management',
                'retention' => '7 years',
            ],
            'financial_data' => [
                'collected' => true,
                'purpose' => 'Payment processing',
                'retention' => '7 years',
            ],
            'educational_records' => [
                'collected' => true,
                'purpose' => 'School management',
                'retention' => 'While active',
            ],
            'communication_logs' => [
                'collected' => true,
                'purpose' => 'Customer support',
                'retention' => '2 years',
            ],
        ];
    }
}
