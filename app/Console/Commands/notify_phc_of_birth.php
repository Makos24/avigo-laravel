<?php

namespace App\Console\Commands;

use App\Http\Controllers\Emails;
use Illuminate\Console\Command;

class notify_phc_of_birth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:notify-phc-of-birth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notifies Public Health Centers (PHC) of births within the week';

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
        echo $email->notify_phc_of_birth()."\n";
    }
}
