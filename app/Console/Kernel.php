<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
        protected function schedule(Schedule $schedule)
    {
        // Sync attendance every 5 minutes
        $schedule->command('attendance:sync')->everyFiveMinutes()->withoutOverlapping();

        // Sync biometric attendance records to staff attendance every 5 minutes
        $schedule->command('attendance:sync-to-staff')->everyFiveMinutes()->withoutOverlapping();

        // Sync Google/public + weekly holidays daily for payroll-ready Holiday status
        $schedule->command('attendance:sync-holidays --with-weekly')->dailyAt('00:30')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }


    protected $commands = [
        Commands\MakeService::class,
        Commands\CheckPharmacyIncome::class,
        Commands\CheckPendingIncome::class,
        Commands\SyncAttendanceCommand::class,
        Commands\SyncGoogleHolidaysCommand::class,
        Commands\ExportHolidayAuditCommand::class,
        Commands\SyncFeaturedDoctors::class,
    ];
}
