<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\LoginController;
use App\Http\Controllers\API\Auth\RegisterController;
use App\Http\Controllers\API\StudentController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\GradeController;
use App\Http\Controllers\API\SubscriptionController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes (Mobile App)
|--------------------------------------------------------------------------
|
| RESTful JSON API for React Native mobile application.
| All routes require tenant context via school_id header.
| Authentication via bearer token (Sanctum).
|
*/

Route::prefix('v1')->group(function () {
    
    // Authentication
    Route::post('login', [LoginController::class, 'login']);
    Route::post('register', [RegisterController::class, 'register']);
    
    Route::middleware(['auth:sanctum', 'tenant.api'])->group(function () {
        
        // Logout
        Route::post('logout', [LoginController::class, 'logout']);
        
        // Refresh token
        Route::post('refresh', [LoginController::class, 'refresh']);
        
        // User profile
        Route::get('user', [LoginController::class, 'profile']);
        Route::put('user', [LoginController::class, 'updateProfile']);
        
        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'overview']);
        Route::get('dashboard/analytics', [DashboardController::class, 'analytics']);
        
        // Students
        Route::apiResource('students', StudentController::class);
        Route::post('students/{student}/photo', [StudentController::class, 'uploadPhoto']);
        Route::get('students/export', [StudentController::class, 'export']);
        
        // Attendance
        Route::prefix('attendance')->group(function () {
            Route::get('/', [AttendanceController::class, 'index']);
            Route::post('mark', [AttendanceController::class, 'mark']);
            Route::post('bulk', [AttendanceController::class, 'bulkMark']);
            Route::get('report', [AttendanceController::class, 'report']);
            Route::get('stats', [AttendanceController::class, 'statistics']);
        });
        
        // Grades
        Route::prefix('grades')->group(function () {
            Route::get('/', [GradeController::class, 'index']);
            Route::post('/', [GradeController::class, 'store']);
            Route::get('student/{student}', [GradeController::class, 'studentGrades']);
            Route::get('report', [GradeController::class, 'report']);
            Route::get('export', [GradeController::class, 'export']);
        });
        
        // Subscription
        Route::prefix('subscription')->group(function () {
            Route::get('/', [SubscriptionController::class, 'show']);
            Route::get('plans', [SubscriptionController::class, 'plans']);
            Route::post('upgrade', [SubscriptionController::class, 'upgrade']);
            Route::post('cancel', [SubscriptionController::class, 'cancel']);
            Route::get('invoices', [SubscriptionController::class, 'invoices']);
            Route::get('invoice/{invoice}', [SubscriptionController::class, 'invoice']);
        });
        
        // Payments
        Route::prefix('payments')->group(function () {
            Route::get('/', [PaymentController::class, 'index']);
            Route::post('process', [PaymentController::class, 'process']);
            Route::get('methods', [PaymentController::class, 'paymentMethods']);
            Route::post('verify-mpesa', [PaymentController::class, 'verifyMpesa']);
            Route::get('receipt/{payment}', [PaymentController::class, 'receipt']);
        });
        
        // Notifications
        Route::get('notifications', [DashboardController::class, 'notifications']);
        Route::post('notifications/read', [DashboardController::class, 'markNotificationsRead']);
    });
    
    // Password reset
    Route::post('password/forgot', [LoginController::class, 'forgotPassword']);
    Route::post('password/reset', [LoginController::class, 'resetPassword']);
    
    // Health check
    Route::get('health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
        ]);
    });
});
