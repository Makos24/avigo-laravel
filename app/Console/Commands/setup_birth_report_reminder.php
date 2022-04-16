<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Edd;
use Carbon\Carbon;
use App\ivr\ivr_functions;
use App\Models\Parent_notification;

class setup_birth_report_reminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminder:birth-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sets up a reminder for birth report to parent\'s whose expected date of delivery is near';

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
        echo $this->setup_reminder();
        //return 'birth report';
    }

    //sets up a reminder notification job for birth report
    function setup_reminder(){
        //get the edds due between now and the next 14 days
        $ivr_function=new ivr_functions();
        $edds=new Edd();
        $edds=$edds::with('parent')
                    ->whereBetween('edd',[Carbon::now(), $ivr_function->getDateAhead(14)]);
        if(!$edds->exists()){
            return 'Nothing found for birth report notification setup\n';
        }
        $edds=$edds->get();
        foreach ($edds as $key => $value) {
            //check if this notification has been pre-registered or not
            $parentNotifs=new Parent_notification();
            $parentNotif=$parentNotifs::where('edd_id',$value['id'])
                                        ->where('parent_id',$value['parent_id']);
            if($parentNotif->exists()){
                $parentNotif=$parentNotif->get()->first();
                if ($parentNotif->edd_notif=='2') {
                   $parentNotif->edd_notif=0;	
                    $parentNotif->save();
                }
            }else{
                //pre-register the notification
                $parentNotif->edd_notif=0;	
                $parentNotif->edd_id=$value['id'];	
                $parentNotif->parent_id=$value['parent_id'];	
                $parentNotif->language_id=$value['language']['id'];	
                $parentNotif->phone=$value['phone'];	
                $parentNotif->mother=$value['mother'];	
                $parentNotif->save();
            }    
        }
        return "Birth Report Setup Done!\n";
    }
}
