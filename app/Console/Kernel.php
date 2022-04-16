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
        $schedule->command('reminder:birth-report')->everyMinute()
        ->appendOutputTo('scheduler.log');
        $schedule->command('reminder:send-birth-report-reminder')->everyMinute()
        ->appendOutputTo('birth-report-reminder.log');
        $schedule->command('report:exceeded_edds')->everyMinute()
        ->appendOutputTo('edd-exceeded.log');
        $schedule->command('notification:edd-registration')->everyMinute()
        ->appendOutputTo('edd-reg-notification.log');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}