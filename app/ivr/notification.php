<?php

namespace App\ivr;

use Twilio\Rest\Client;
use App\Models\Parent_notification;
use App\Models\Birth_notification;

/**
 * this class manages notification calls
 */
class notification
{
    function __construct()
    {
        
    }
    function notifyContactWithThisEddID($eddID=''){
        $notifs=new Birth_notification();
        if($eddID==''){
            $notif=$notifs::where('notified','0')->get();
        }else{
            $notif=$notifs::where('edd_id',$eddID)
            ->where('notified','0')->get();
        }        
        foreach ($notifs as $key => $value) {
            try{
                $client = new Client(env('ACCOUNT_SID'),env('AUTH_TOKEN'));
                $client->account->calls->create(  
                    $value['phone'],
                    env('TWILIO_NUMBER'),
                    array(
                    "method" => "POST",
                    "statusCallback" => URL('')."/api/notify-contacts-callback?EddID=".$value['EddID'],
                    "statusCallbackEvent" => ["completed"],
                    "statusCallbackMethod" => "POST",
                    "url" => URL('')."/api/notify-contacts?Type=Contact_Notif&EddID=".$value['EddID']
                    )
                );
            }catch (\Throwable $ex){
                return false;
            } 
        }
        return true;
    }
    function birth_report_reminder(){
        $reminders=new Parent_notification();
        $reminder=$reminders::with('language')->where('edd_notif','0')->get();
        foreach ($reminder as $key => $value) {
            try{
                $client = new Client(env('ACCOUNT_SID'),env('AUTH_TOKEN'));
                $client->account->calls->create(  
                    $value['phone'],
                    env('TWILIO_NUMBER'),
                    array(
                    "method" => "POST",
                    "statusCallback" => URL('')."/api/birth-report-reminder-callback?EddID=".$value['edd_id'],
                    "statusCallbackEvent" => ["completed"],
                    "statusCallbackMethod" => "POST",
                    "url" => URL('')."/api/birth-report-reminder?EddID=".$value['edd_id']."&Language=".$value['language']['name']."&ParentID=".$value['parent_id']."&Mother=".$value['mother']
                    )
                );
            }catch (\Throwable $ex){
                return $ex->getMessage();
            } 
        }
        return true;
    }
    
    function edd_registration_notif(){
        $notifs=new Parent_notification();
        $notif=$notifs::with('language')->where('notif1','0')->get();
        foreach ($notif as $key => $value) {
            try{
               $client = new Client(env('ACCOUNT_SID'),env('AUTH_TOKEN'));
                $client->account->calls->create(  
                    $value['phone'],
                    env('TWILIO_NUMBER'),
                    array(
                    "method" => "POST",
                    "statusCallback" => URL('')."/api/edd-registration-notification-callback?EddID=".$value['edd_id'],
                    "statusCallbackEvent" => ["completed"],
                    "statusCallbackMethod" => "POST",
                    "url" => URL('')."/api/edd-registration-notification?Type=EDD_Reg&EddID=".$value['edd_id']."&Language=".$value['language']['name']."&ParentID=".$value['parent_id']."&Mother=".$value['mother']
                    )
                );
            }catch (\Throwable $ex){
                return $ex->getMessage();
            } 
        }
        return true;
    }
}
