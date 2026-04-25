<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\School\SchoolRegistrationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Tenant isolation: All authenticated routes require TenantMiddleware
| which binds school context to every request.
|
*/

// Landing page
Route::get('/', function () {
    return inertia('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('welcome');

// Authentication routes (no tenant context required)
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');
});

// Self-registration for schools (OPEN ONBOARDING - no manual provisioning)
Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
});

// Authenticated routes WITH tenant isolation
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // School profile management (within tenant)
    Route::prefix('school')->name('school.')->group(function () {
        Route::get('profile', [SchoolRegistrationController::class, 'edit'])
            ->name('profile');
        Route::put('profile', [SchoolRegistrationController::class, 'update'])
            ->name('profile.update');
    });
});

// Super-admin routes (no tenant scope, but auth required)
Route::middleware(['auth', 'role:system_admin'])->group(function () {
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('schools', \App\Http\Controllers\Admin\SchoolController::class)
            ->name('schools.index');
    });
});
