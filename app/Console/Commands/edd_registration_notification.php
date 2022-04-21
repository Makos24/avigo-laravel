<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\ivr\notification;

class edd_registration_notification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:edd-registration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This notifies parents of their initial registration on Avigo Health Platform';

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
        $response=$notif->edd_registration_notif();
        if($response===true){
            echo "Edd Registration Notification Sent!\n";
        }else{
            echo "Edd Registration Notification Not Sent! Reason: ".$response."\n";
        }
    }
}
