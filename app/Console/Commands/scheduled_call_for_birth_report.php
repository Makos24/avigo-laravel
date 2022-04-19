<?php

namespace App\Console\Commands;

use App\Http\Controllers\Emails;
use Illuminate\Console\Command;

class scheduled_call_for_birth_report extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:scheduled-call-for-birth-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This script checks for birth reports that could not be received due to unavailability of parent or mother\'s name audio file';

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
        echo $email->scheduled_call_for_birth_report_notification()."\n";
    }
}
