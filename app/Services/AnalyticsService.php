<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Analytics Service
 * 
 * Provides tenant-aware analytics and reporting.
 * All data is filtered by school_id.
 */
class AnalyticsService
{
    /**
     * @var School
     */
    protected $school;

    /**
     * Constructor
     */
    public function __construct(School $school)
    {
        $this->school = $school;
    }

    /**
     * Get dashboard overview statistics
     */
    public function getOverview(): array
    {
        return [
            'students' => $this->getStudentCount(),
            'monthly_enrollments' => $this->getMonthlyEnrollments(),
            'attendance_rate' => $this->getAttendanceRate(),
            'average_grade' => $this->getAverageGrade(),
            'revenue' => $this->getMonthlyRevenue(),
            'active_subscriptions' => $this->getActiveSubscriptionCount(),
        ];
    }

    /**
     * Get student count
     */
    public function getStudentCount(): int
    {
        $cacheKey = "school_{$this->school->id}_student_count";

        return Cache::remember($cacheKey, 3600, function () {
            return $this->school->students()->count();
        });
    }

    /**
     * Get enrollments this month
     */
    public function getMonthlyEnrollments(): int
    {
        return $this->school->students()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    /**
     * Get attendance rate for current month
     */
    public function getAttendanceRate(): float
    {
        // Simplified calculation
        // In production, would join with attendance records
        return 92.5; // Placeholder
    }

    /**
     * Get average grade across all students
     */
    public function getAverageGrade(): float
    {
        // Simplified calculation
        // In production, would calculate from grade records
        return 78.3; // Placeholder
    }

    /**
     * Get monthly revenue
     */
    public function getMonthlyRevenue(): float
    {
        return $this->school->payments()
            ->where('status', 'completed')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');
    }

    /**
     * Get active subscription count
     */
    public function getActiveSubscriptionCount(): int
    {
        // For this school, it's 0 or 1
        return $this->school->subscription && $this->school->subscription->isActive() ? 1 : 0;
    }

    /**
     * Get enrollment trends
     */
    public function getEnrollmentTrends(int $months = 6): array
    {
        $data = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthKey = $date->format('Y-m');

            $count = $this->school->students()
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();

            $data[] = [
                'month' => $monthKey,
                'enrollments' => $count,
            ];
        }

        return $data;
    }

    /**
     * Get revenue trends
     */
    public function getRevenueTrends(int $months = 6): array
    {
        $data = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthKey = $date->format('Y-m');

            $revenue = $this->school->payments()
                ->where('status', 'completed')
                ->whereMonth('paid_at', $date->month)
                ->whereYear('paid_at', $date->year)
                ->sum('amount');

            $data[] = [
                'month' => $monthKey,
                'revenue' => $revenue,
            ];
        }

        return $data;
    }

    /**
     * Get top performing students
     */
    public function getTopStudents(int $limit = 5): array
    {
        // Simplified - would join with grades in production
        return $this->school->students()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($student) {
                return [
                    'id' => $student->id,
                    'name' => $student->first_name . ' ' . $student->last_name,
                    'grade' => rand(60, 100), // Placeholder
                ];
            })
            ->toArray();
    }

    /**
     * Get attendance summary
     */
    public function getAttendanceSummary(): array
    {
        // Simplified - would calculate from attendance records
        return [
            'total_days' => 20,
            'present_days' => 18,
            'absent_days' => 2,
            'late_days' => 3,
            'rate' => 90.0,
        ];
    }

    /**
     * Get subscription analytics
     */
    public function getSubscriptionAnalytics(): array
    {
        $subscription = $this->school->subscription;

        if (!$subscription) {
            return [
                'active' => false,
                'plan' => null,
                'days_remaining' => 0,
                'usage_percentage' => 0,
            ];
        }

        $daysRemaining = $subscription->daysRemaining();
        $studentLimit = $subscription->getStudentLimit();
        $currentStudents = $this->school->students()->count();

        $usagePercentage = $studentLimit > 0
            ? min(100, ($currentStudents / $studentLimit) * 100)
            : 0;

        return [
            'active' => $subscription->isActive(),
            'plan' => $subscription->plan_type,
            'days_remaining' => $daysRemaining,
            'usage_percentage' => round($usagePercentage, 1),
            'student_limit' => $studentLimit ?? 'unlimited',
            'current_students' => $currentStudents,
        ];
    }

    /**
     * Export analytics data
     */
    public function exportData(string $format = 'json'): array
    {
        $data = [
            'school' => [
                'name' => $this->school->name,
                'email' => $this->school->email,
                'county' => $this->school->county,
                'created_at' => $this->school->created_at->toIso8601String(),
            ],
            'overview' => $this->getOverview(),
            'enrollment_trends' => $this->getEnrollmentTrends(12),
            'revenue_trends' => $this->getRevenueTrends(12),
            'subscription' => $this->getSubscriptionAnalytics(),
            'generated_at' => now()->toIso8601String(),
        ];

        return $data;
    }

    /**
     * Track custom event
     */
    public function trackEvent(string $event, array $properties = []): void
    {
        // In production, would send to analytics service
        // For now, log the event
        $logData = [
            'school_id' => $this->school->id,
            'event' => $event,
            'properties' => $properties,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($this->school->settings?->analytics_enabled) {
            Cache::push("school_{$this->school->id}_events", $logData, 3600);
        }
    }

    /**
     * Get cached analytics
     */
    public function getCached(string $key, callable $callback, int $ttl = 3600)
    {
        $cacheKey = "school_{$this->school->id}_analytics_{$key}";
        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Clear analytics cache
     */
    public function clearCache(): void
    {
        Cache::tags(["school_{$this->school->id}"])->flush();
    }
}
