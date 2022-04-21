<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Edd;
use Carbon\Carbon;
use App\Models\Exceeded_edd;

class get_unreported_births extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:exceeded_edds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gets all EDD records that are past reporting period by 30 days';

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
        try {
            $edds=new EDD();
            $edds=$edds::where('survey_completed',0)
                        ->whereDate('edd','<',Carbon::now()->subDays(30))
                        ->orderBy('edd','DESC')
                        ->get();
            foreach ($edds as $edd) {
                //mark this as overdue edd
                $edd->survey_completed=2;
                //push this record to the exceeded edd table
                $exceed_edds=new Exceeded_edd();
                $exceed_edd=$exceed_edds::where('edd_id',$edd->id);
                if(!$exceed_edd->exists()){
                    $exceed_edds->edd_id=$edd->id;
                    $exceed_edds->save();
                }
                $edd->save(); 
            }
            echo "Done!\n";
        } catch (\Throwable $th) {
            echo "Ooops! Something went wrong\n".$th->getMessage()."\n";
        }      
    }
}
