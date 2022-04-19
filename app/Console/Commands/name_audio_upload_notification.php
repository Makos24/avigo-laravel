<?php

namespace App\Console\Commands;

use App\Http\Controllers\Emails;
use Illuminate\Console\Command;

class name_audio_upload_notification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:name-audio-upload';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends a reminder email for names without audio files to be updated';

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
        echo $email->name_audio_upload_notification()."\n";
    }
}
