<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\ivr\notification;

class notify_contacts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:contacts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends notification of births to contacts';

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
        $response=$notif->notifyContactWithThisEddID();
        if($response===true){
            echo "Notification Sent!\n";
        }else{
            echo "Notification Not Sent! Reason: ".$response."\n";
        }
    }
}
