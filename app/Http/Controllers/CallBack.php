<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ivr\call_thread;
use App\ivr\ivr_functions;
use App\Models\Birth_notification;
use App\Models\Edd;
use App\Models\Parent_notification;

class CallBack extends Controller
{
    function ivrIncomingCallBack(Request $request)
    {
        $callThread = new call_thread();
        $callThread->initialize_call_back($request);
        $callThread->saveCallBehaviour('CallBack\ivrIncomingCallBack');
        //verify what this code is for
        /* $edds = new Edd();
        $edd = $edds::where('sessionid', $callThread->sessionId);
        if ($edd->exists()) {
            $edd = $edd->first();
            if ($edd->current_call_state!='SessionEnd' AND $edd->previous_call_state!='QuestionEnd' AND $edd->was_last_input_correct==1) {
                //ensure call state is correct
                if ($edd->current_call_state!='Question3') {
                    $callThread->CallStateKeeperErrorInput($edd->previous_call_state,$edd->id,1); 
                }else{
                    if ($edd->number_delivered=='1') {
                        $edd->previous_call_state='Question1';
                        $edd->current_call_state='Question2';
                    }else{
                        $edd->previous_call_state='Question2';
                        $edd->current_call_state='Question2C';
                    }
                    $edd->save();
                }
            }           
        } */
    }
    function notifyContactsCallBack(Request $request)
    {
        $callThread = new call_thread();
        $callThread->initialize_call_back($request);
        $callThread->saveCallBehaviour('CallBack\notifyContactsCallBack');
        //get the notification thread
        $notifs = new Birth_notification();
        $notif = $notifs::where('edd_id', $request->query('EddID'))
            ->where('phone', $callThread->getCaller())
            ->where('notified', '0');
        if ($notif->exists()) {
            $notif = $notif->first();
            if ($callThread->callSessionState!='no-answer' AND $callThread->callSessionState!='busy' AND $callThread->callSessionState!='failed') {
                //set as notified
                $notif->notified=1;
                $notif->save();
            }             
        }
    }
    
    function eddRegistrationNotificationCallback(Request $request)
    {
        $callThread = new call_thread();
        $callThread->initialize_call_back($request);
        $callThread->saveCallBehaviour('CallBack\eddRegistrationNotificationCallback');
        //get the notification thread
        $notifs = new Parent_notification();
        $notif = $notifs::where('edd_id', $request->query('EddID'))
            ->where('phone', $callThread->getCaller())
            ->where('notif1', '0');
        if ($notif->exists()) {
            $notif = $notif->first();
            if ($callThread->callSessionState!='no-answer' AND $callThread->callSessionState!='busy' AND $callThread->callSessionState!='failed') {
                //set as notified
                $notif->notif1=1;
                $notif->save();
            }             
        }
    }

    function birthReportReminderCallback(Request $request)
    {
        $callThread = new call_thread();
        $callThread->initialize_call_back($request);
        $callThread->saveCallBehaviour('CallBack\birthReportReminderCallback');
        //get the notification thread
        $notifs = new Parent_notification();
        $notif = $notifs::where('edd_id', $request->query('EddID'))
            ->where('phone', $callThread->getCaller())
            ->where('edd_notif', '0');
        if ($notif->exists()) {
            $notif = $notif->first();
            if ($callThread->callSessionState!='no-answer' AND $callThread->callSessionState!='busy' AND $callThread->callSessionState!='failed') {
                //set as notified
                $notif->edd_notif=1;
                $notif->save();
            }             
        }
    }
}
