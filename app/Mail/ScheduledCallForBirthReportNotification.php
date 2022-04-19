<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ScheduledCallForBirthReportNotification extends Mailable
{
    use Queueable, SerializesModels;
    public $firstname;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($firstname)
    {
        $this->firstname=$firstname;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.scheduled-call-for-birth-report')
        ->text('emails.scheduled-call-for-birth-report-plaintext')
        ->subject('Albarka Scheduled call Notice');
    }
}
