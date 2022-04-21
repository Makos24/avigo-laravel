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
        $schedule->command('reminder:birth-report')->daily()
        ->hourly()
        ->timezone('Africa/Lagos')
        ->between('08:00','21:00')
        ->withoutOverlapping()
        ->appendOutputTo('scheduler.log');

        $schedule->command('reminder:send-birth-report-reminder')->daily()
        ->hourly()
        ->timezone('Africa/Lagos')
        ->between('08:00','21:00')
        ->withoutOverlapping()
        ->appendOutputTo('birth-report-reminder.log');

        $schedule->command('report:exceeded_edds')->daily()
        ->hourly()
        ->timezone('Africa/Lagos')
        ->between('08:00','21:00')
        ->withoutOverlapping()
        ->appendOutputTo('edd-exceeded.log');

        $schedule->command('notification:edd-registration')->daily()
        ->hourly()
        ->timezone('Africa/Lagos')
        ->between('08:00','21:00')
        ->withoutOverlapping()
        ->appendOutputTo('edd-reg-notification.log');

        $schedule->command('notification:name-audio-upload')
        ->weekly()
        ->mondays()
        ->timezone('Africa/Lagos')
        ->at('21:00')
        ->withoutOverlapping()
        ->appendOutputTo('name-upload-notification.log');

        $schedule->command('notification:notify-phc-of-birth')
        ->weekly()
        ->fridays()
        ->timezone('Africa/Lagos')
        ->at('08:00')
        ->withoutOverlapping()
        ->appendOutputTo('notify-phc-of-birth.log');        

        $schedule->command('notification:reminder-phc-of-birth')
        ->weekly()
        ->mondays()
        ->timezone('Africa/Lagos')
        ->at('08:00')
        ->withoutOverlapping()
        ->appendOutputTo('reminder-phc-of-birth.log');

        $schedule->command('notification:no-notify-phc-of-birth')
        ->weekly()
        ->fridays()
        ->timezone('Africa/Lagos')
        ->at('08:00')
        ->withoutOverlapping()
        ->appendOutputTo('no-notify-phc-of-birth.log');

        $schedule->command('notification:scheduled-call-for-birth-report')
        ->weekly()
        ->fridays()
        ->timezone('Africa/Lagos')
        ->at('21:00')
        ->withoutOverlapping()
        ->appendOutputTo('scheduled-call-for-birth-report.log');
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