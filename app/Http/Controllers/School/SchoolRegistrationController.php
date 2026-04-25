<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;

/**
 * School Registration Controller (within tenant)
 * 
 * Allows school admins to update their own school profile.
 * All actions are constrained to the current tenant.
 */
class SchoolRegistrationController extends Controller
{
    /**
     * Show the school profile edit form.
     */
    public function edit(): Response
    {
        $school = app('tenant');

        return Inertia::render('School/Edit', [
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'email' => $school->email,
                'phone' => $school->phone,
                'county' => $school->county,
                'subscription_status' => $school->subscription_status,
                'trial_ends_at' => $school->trial_ends_at?->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Update the school profile.
     */
    public function update(Request $request): \Illuminate\Http\RedirectResponse
    {
        $school = app('tenant');

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:schools,email,' . $school->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'county' => ['nullable', 'string', 'max:255'],
        ]);

        $school->update($request->only(['name', 'email', 'phone', 'county']));

        return back()->with('success', 'School profile updated successfully.');
    }
}
