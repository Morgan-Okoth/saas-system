<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Auth\AuthenticationException;

/**
 * Exception Handler
 * 
 * Centralized exception handling with tenant-aware logging.
 */
class Handler extends ExceptionHandler
{
    /**
     * Register exception handling callbacks.
     */
    public function register(): void
    {
        // Log all exceptions
        $this->reportable(function (Throwable $e) {
            $this->logException($e);
        });

        // Render 404 for model not found
        $this->renderable(function (ModelNotFoundException $e, $request) {
            return $this->handleModelNotFound($e, $request);
        });
    }

    /**
     * Log exception with tenant context
     */
    protected function logException(Throwable $e): void
    {
        $context = [
            'message' => $e->getMessage(),
            'class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode(),
            'trace' => $e->getTraceAsString(),
        ];

        // Add tenant context if available
        if ($tenant = app('tenant', null)) {
            $context['tenant_id'] = $tenant->id;
            $context['tenant_name'] = $tenant->name;
        }

        // Add user context if authenticated
        if (auth()->check()) {
            $context['user_id'] = auth()->id();
            $context['user_email'] = auth()->user()->email;
            $context['user_role'] = auth()->user()->role;
        }

        // Log based on exception type
        if ($e instanceof \Illuminate\Database\QueryException) {
            logger()->error('Database query exception', $context);
        } elseif ($e instanceof AuthenticationException) {
            logger()->warning('Authentication exception', $context);
        } elseif ($e instanceof NotFoundHttpException) {
            logger()->warning('HTTP not found', $context);
        } else {
            logger()->error('Unhandled exception', $context);
        }

        // Security logging for CSRF and access violations
        if ($e instanceof \Illuminate\Session\TokenMismatchException) {
            logger()->warning('CSRF token mismatch', $context);
        }
    }

    /**
     * Handle model not found exception
     */
    protected function handleModelNotFound(ModelNotFoundException $e, $request)
    {
        $model = $e->getModel();
        $ids = $e->getIds();

        // Log the 404 with tenant context
        logger()->warning('Model not found', [
            'model' => $model,
            'ids' => $ids,
            'tenant_id' => app('tenant')?->id,
            'url' => $request->fullUrl(),
        ]);

        // Return 404 response
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => 'Resource not found',
                'message' => 'The requested resource does not exist or you do not have permission to access it.',
            ], 404);
        }

        // For web requests, show custom 404 page
        return response()->view('errors.404', [
            'message' => 'The requested resource was not found.',
        ], 404);
    }

    /**
     * Render an exception into an HTTP response
     */
    public function render($request, Throwable $e)
    {
        // Custom handling for tenant-specific exceptions
        if ($e instanceof \RuntimeException && 
            str_contains($e->getMessage(), 'Cannot create without school_id')) {
            
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Tenant context required',
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect('/login')->withErrors([
                'email' => 'Your account is not properly configured. Please contact support.',
            ]);
        }

        return parent::render($request, $e);
    }

    /**
     * Report or log an exception
     */
    public function report(Throwable $e): void
    {
        // Don't report certain exceptions
        if ($this->shouldntReport($e)) {
            return;
        }

        // Check if we should report this exception type
        if ($this->shouldReport($e)) {
            // Send to Sentry or other monitoring services (if configured)
            if (app()->bound('sentry') && $this->shouldReportToSentry($e)) {
                app('sentry')->captureException($e);
            }
        }

        parent::report($e);
    }

    /**
     * Determine if exception should be reported to Sentry
     */
    protected function shouldReportToSentry(Throwable $e): bool
    {
        // Don't report 404s or 422s to Sentry
        if ($e instanceof NotFoundHttpException || 
            ($e instanceof \Illuminate\Http\Exception\HttpResponseException && $e->getStatusCode() === 422)) {
            return false;
        }

        // Report all other errors in production
        return app()->environment('production');
    }

    /**
     * Determine if exception should be reported
     */
    protected function shouldReport(Throwable $e): bool
    {
        // Don't report common HTTP exceptions
        if ($e instanceof NotFoundHttpException) {
            return false;
        }

        // Don't report validation errors
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return false;
        }

        return parent::shouldReport($e);
    }
}
