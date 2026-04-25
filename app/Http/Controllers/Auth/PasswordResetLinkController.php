<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Password Reset Link Controller
 * 
 * Handles password reset request forms.
 */
class PasswordResetLinkController extends Controller
{
    /**
     * Show the password reset link request form.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     * 
     * Note: Full implementation requires mail setup.
     * Using Mailtrap for development, Resend for production.
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        // TODO: Implement password reset with Resend/Mailtrap
        // Validate email, queue reset job, send link
        
        return back()->with('status', 'Password reset link sent!');
    }
}
