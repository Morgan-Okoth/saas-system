<?php

namespace App\Http\Controllers;

use App\Models\School;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard Controller
 * 
 * Main tenant-aware dashboard after authentication.
 * All routes through this controller require tenant context.
 */
class DashboardController extends Controller
{
    /**
     * Show the dashboard.
     * 
     * Tenant context is guaranteed by 'tenant' middleware.
     */
    public function index(): Response
    {
        $school = app('tenant');

        // Quick stats for the dashboard
        $stats = [
            'total_students' => $school->students()->count(),
            'subscription_status' => $school->subscription_status,
            'is_trial' => $school->isTrial(),
            'is_expired' => $school->isExpired(),
            'trial_ends_at' => $school->trial_ends_at?->format('M d, Y'),
        ];

        return Inertia::render('Dashboard', [
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'email' => $school->email,
                'county' => $school->county,
                'subscription_status' => $school->subscription_status,
            ],
            'stats' => $stats,
        ]);
    }
}
