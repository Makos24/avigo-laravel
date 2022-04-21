<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NoBirthNotifyPhcOfBirth extends Mailable
{
    use Queueable, SerializesModels;
    public $phc_id;
    public $phc_name;
    public $phc_phone;
    public $phc_facility_uid;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($phc_id,$phc_name,$phc_phone,$phc_facility_uid)
    {
        $this->phc_id=  $phc_id;
        $this->phc_name= $phc_name;
        $this->phc_phone= $phc_phone;
        $this->phc_facility_uid=$phc_facility_uid;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.no-birth-notify-phc-of-birth')
        ->text('emails.no-birth-notify-phc-of-birth-plaintext')
        ->subject('Albarka No New Birth Notification');
    }
}
