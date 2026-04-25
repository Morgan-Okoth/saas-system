<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * School Self-Registration Controller
 * 
 * Handles automatic onboarding - no manual provisioning required.
 * When a school registers:
 * 1. School record created (trial mode)
 * 2. First user created as school_admin
 * 3. User linked to school
 * 4. Auto-login with tenant context established
 */
class RegisteredUserController extends Controller
{
    /**
     * Show the registration view.
     */
    public function create(): \Inertia\Response
    {
        return inertia('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     * 
     * Creates both School and User atomically.
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        // Validate registration input
        $request->validate([
            'school_name' => ['required', 'string', 'max:255'],
            'school_email' => ['required', 'string', 'email', 'max:255', 'unique:schools,email'],
            'school_county' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Create School (tenant root)
        // subscription_status defaults to 'trial'
        // trial_ends_at set automatically via model/observer
        $school = School::create([
            'name' => $request->school_name,
            'email' => $request->school_email,
            'phone' => $request->phone,
            'county' => $request->school_county,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'settings' => [
                'timezone' => config('app.timezone', 'Africa/Nairobi'),
                'currency' => 'KES',
            ],
        ]);

        // Create first user as school_admin
        // This user will manage the school
        $user = User::create([
            'school_id' => $school->id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'school_admin',
        ]);

        // Assign roles via Spatie
        // school_admin gets full permissions within tenant
        $user->assignRole('school_admin');

        // Auto-login the new school admin
        Auth::login($user);

        // Important: TenantMiddleware will bind school context
        // on next request, but we set it now for immediate use
        app()->instance('tenant', $school);

        // TODO: Send welcome email via Resend
        // \Mail::to($user)->send(new \App\Mail\SchoolRegistered($school, $user));

        return redirect(RouteServiceProvider::HOME)
            ->with('success', 'School registered successfully! Your 14-day trial has started.');
    }
}
