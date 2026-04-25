<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\ProcessSubscriptionWebhook;

/**
 * Console Kernel
 * 
 * Handles scheduled tasks for VPS deployment:
 * - Subscription expiration checks
 * - Queue worker restarts
 * - Failed job cleanup
 */
class Kernel extends ConsoleKernel
{
    /**
     * Artisan commands to register.
     */
    protected $commands = [
        // Add custom Artisan commands here
    ];

    /**
     * Schedule application tasks.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Check for expired subscriptions every hour
        $schedule->call(function () {
            \App::make(\App\Services\SubscriptionService::class)->checkExpiredSubscriptions();
        })->hourly();

        // Retry failed webhook processing
        $schedule->command('queue:retry-failed --hours=24')->daily();

        // Clear expired cache entries
        $schedule->command('cache:prune-stale-tags')->daily();

        // Optimize queues (remove old completed jobs)
        $schedule->command('queue:flush')->weekly();
    }

    /**
     * Register application commands.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
