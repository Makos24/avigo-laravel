<?php

namespace App\Console\Commands;

use App\Http\Controllers\Emails;
use Illuminate\Console\Command;

class reminder_phc_of_birth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:reminder-phc-of-birth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reminders Public Health Centers (PHC) of births last week';

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
        $email=new Emails();
        echo $email->reminder_phc_of_birth()."\n";
    }
}
