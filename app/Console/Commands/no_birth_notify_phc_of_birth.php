<?php

namespace App\Console\Commands;

use App\Http\Controllers\Emails;
use Illuminate\Console\Command;

class no_birth_notify_phc_of_birth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:no-notify-phc-of-birth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notifies Public Health Centers (PHC) of null births within the week';

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
        echo $email->no_birth_notify_phc_of_birth()."\n";
    }
}
