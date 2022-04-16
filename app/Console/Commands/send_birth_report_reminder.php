<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\ivr\notification;
class send_birth_report_reminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminder:send-birth-report-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends the reminder for parents to make birth reports as their expected date of delivery is near';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $notif=new notification;
        $response=$notif->birth_report_reminder();
        if($response===true){
            echo "Birth Report Reminder Sent!\n";
        }else{
            echo "Birth Report Reminder Not Sent! Reason: ".$response."\n";
        }
    }
}
