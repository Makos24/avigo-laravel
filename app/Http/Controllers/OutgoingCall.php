<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ivr\call_thread;
use App\ivr\ivr_functions;
use App\Models\Birth_notification;
/**
 * handles outgoing calls
 */
class OutgoingCall extends Controller
{
    /**
     * sends a birth report reminder to parents whose expected dates of deliveries are near
     * Begin
     */
    function birthReportReminder(Request $request){
        $callThread = new call_thread();
        $callThread->initialize_call($request);
        $callThread->saveCallBehaviour('OutgoingCall\birthReportReminder');
        //get the audio for this call
        switch ($request->query('Language')) {
            case 'english':
                //$response=$this->composeBirthReportReminderCallEnglish($request->query('ParentID'),$request->query('Mother'),$request->query('Language'));
                break;
            case 'igbo':
                //$response=$this->composeBirthReportReminderCallIgbo($request->query('ParentID'),$request->query('Mother'),$request->query('Language'));
                break;
            case 'hausa':
                $response=$this->composeBirthReportReminderCallHausa($request->query('ParentID'),$request->query('Mother'),$request->query('Language'));
                break;
            case 'yoruba':
                //$response=$this->composeBirthReportReminderCallYoruba($request->query('ParentID'),$request->query('Mother'),$request->query('Language'));
                break;      
            default:
                //$response=$this->composeBirthReportReminderCallEnglish($request->query('ParentID'),$request->query('Mother'),$request->query('Language'));
            break;
        }
        return response($response)->header('Content-type', 'text/xml');
    }
    function composeBirthReportReminderCallHausa($parentID,$mother,$language){
        $ivr_function=new ivr_functions();
        //get the parent name
        $father=$ivr_function->fathersName($parentID);
        $arrangedNames=$ivr_function->callOutGenericName($father);
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //greeting
        $response .= "<Play>".URL('')."/audio/clips/$language/alhamdulilah.mp3</Play>";
        //name of father    
        $response .= $arrangedNames;
        $response .= "<Play>".URL('')."/audio/clips/$language/ranar-haifuwa-matar-ka.mp3</Play>";
        //mother's name
        $response .=$ivr_function->callOutGenericName($ivr_function->getName($mother));
        $response .= "<Play>".URL('')."/audio/clips/$language/birth-report-reminder-remainder-audio.mp3</Play>";
        //add particular wife's name
        $response .= '</Response>';
        return $response;
    }
    /**
     * sends a birth report reminder to parents whose expected dates of deliveries are near
     * Ends
     */

    /**
     * sends a birth notifications to contacts
     * Begins
     */
    function notifyContacts(Request $request)
    {
        $callThread = new call_thread();
        $callThread->initialize_call($request);
        $callThread->saveCallBehaviour('OutgoingCall\notifyContacts');
        //get notification content for this contact
        $notifs = new Birth_notification();
        $notif = $notifs::where('edd_id', $callThread->getEddId())
            ->where('phone', $callThread->getCaller())
            ->where('notified', '0')
            ->with('edd');
        if ($notif->exists()) {
            $notif = $notif->first();
            switch ($callThread->getLanguage()) {
                case 'english':
                    //$response= composeContactNotifCallEnglish($dbc,$rows['EddID'],$rows['ParentID'],$rows['DoC'],$rows['NumberDelivered'],$rows['Male'],$rows['Female'],$language);
                    break;
                case 'igbo':
                    $response = $this->composeContactNotifCallIgbo($notif->edd_Id, $notif->edd->parent_id, $notif->edd->doc, $notif->edd->number_delivered, $notif->edd->male, $notif->edd->female, $callThread->getLanguage());
                    break;
                case 'hausa':
                    $response = $this->composeContactNotifCallHausa($notif->edd_Id, $notif->edd->parent_id, $notif->edd->doc, $notif->edd->number_delivered, $notif->edd->male, $notif->edd->female, $callThread->getLanguage());
                    break;
                case 'yoruba':
                    $response = $this->composeContactNotifCallYoruba($notif->edd_Id, $notif->edd->parent_id, $notif->edd->doc, $notif->edd->number_delivered, $notif->edd->male, $notif->edd->female, $callThread->getLanguage());
                    break;
                default:
                    $response = $this->composeContactNotifCallEnglish($notif->edd_Id, $notif->edd->parent_id, $notif->edd->doc, $notif->edd->number_delivered, $notif->edd->male, $notif->edd->female, $callThread->getLanguage());
                    break;
            }
        } else {
            $response  = '<?xml version="1.0" encoding="UTF-8"?>';
            $response .= '<Response>';
            $response .= '<Play>' . URL('') . '/audio/clips/' . $callThread->getLanguage() . '/notification-already-sent.mp3</Play>';
            $response .= '</Response>';
        }

        return response($response)->header('Content-type', 'text/xml');
    }
    function composeContactNotifCallIgbo($eddID, $parentID, $doc, $numberOfChildren, $male, $female, $language)
    {
        $response = '';
        return $response;
    }
    function composeContactNotifCallYoruba($eddID, $parentID, $doc, $numberOfChildren, $male, $female, $language)
    {
        $response = '';
        return $response;
    }
    function composeContactNotifCallEnglish($eddID, $parentID, $doc, $numberOfChildren, $male, $female, $language)
    {
        $response = '';
        return $response;
    }

    function composeContactNotifCallHausa($eddID, $parentID, $doc, $numberOfChildren, $male, $female, $language)
    {
        $ivr_functions = new ivr_functions();
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //prefix to name
        $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/alhamdulilahi-matar.mp3</Play>';
        $gender = 'Male';
        $arrangedNames = '';
        //get the parent name
        $name = $ivr_functions->fathersName($parentID);
        //break multiple names into single names and return a playable TwiML
        $arrangedNames = $ivr_functions->callOutName($name, $language);
        $response .= $arrangedNames;
        //suffix to name    
        $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/ta-haifi.mp3</Play>';
        //check the number of children
        if ($numberOfChildren > 1) {
            //more than one child was born
            if ($numberOfChildren > 2) {
                //more than two children
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/jarirai.mp3</Play>';
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/guda.mp3</Play>';
                //get the number of children in words
                $response .= $ivr_functions->callNumbersInWords($numberOfChildren, $language);
            } else {
                //twins
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/twins.mp3</Play>';
            }
            //get the gender of the children
            if (0 < $male and $male == $numberOfChildren) {
                //All are males
                $gender = 'Male';
                if ($numberOfChildren == 2) {
                    $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/kuma.mp3</Play>';
                }
                //check if these are twins, use the right adverbs
                $response .= $this->ivr_function->checkTwins($numberOfChildren, $language);
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/maza.mp3</Play>';
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/ne.mp3</Play>';
            } elseif (0 < $female and $female == $numberOfChildren) {
                //All are females
                $gender = 'Female';
                if ($numberOfChildren == 2) {
                    $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/kuma.mp3</Play>';
                }
                //check if these are twins, use the right adverbs
                $response .= $this->ivr_function->checkTwins($numberOfChildren, $language);
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/mata.mp3</Play>';
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/ne.mp3</Play>';
            } else {
                //not all are females nor all males
                //get the number of males in words
                if ($male == 1) {
                    //a boy
                    $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/namiji.mp3</Play>';
                    $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/dakuma.mp3</Play>';
                } else {
                    //a number of boys
                    $response .= $ivr_functions->callNumbersInWords($male, $language);
                    $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/maza.mp3</Play>';
                }
                //and
                //get the number of females in words
                if ($female == 1) {
                    //a girl
                    $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/mace.mp3</Play>';
                } else {
                    //a number of girls
                    //put the subject here if the number of males is 1
                    if ($male == 1) {
                        $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/mata.mp3</Play>';
                    }
                    //call the number of females
                    $response .= $ivr_functions->callNumbersInWords($female, $language);
                    //put the subject here if the number of males is greater than one
                    if ($male != 1) {
                        $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/mata.mp3</Play>';
                    }
                }
            }
        } else {
            //one child was born
            //check the gender of the child
            if ($male == 0) {
                //child is female
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/jaririya.mp3</Play>';
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/mace.mp3</Play>';
            } else {
                //child is male
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/jariri.mp3</Play>';
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/namiji.mp3</Play>';
            }
        }
        //check the naming ceremony date
        $today = date('Y-m-d');
        if ($doc > $today) {
            $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/yayin-da.mp3</Play>';
            $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/zaayi.mp3</Play>';
            $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/radin-sunan.mp3</Play>';
            //naming ceremony is the near future
            //get the difference between the naming ceremony date and today
            $daysToNaming = date_diff(date_create($doc), date_create($today))->days;
            if ($daysToNaming == 1) {
                //naming is tomorrow
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/gobe.mp3</Play>';
                //is
            } else {
                //days
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/a-kwanaki.mp3</Play>';
                $response .= $ivr_functions->callNumbersInWords($daysToNaming, $language);
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/masu-zuwa.mp3</Play>';
            }
        } elseif ($doc = $today) {
            //naming ceremony is today
            $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/kuma.mp3</Play>';
            $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/a-yaune.mp3</Play>';
            $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/zaayi.mp3</Play>';
            $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/radin-sunan.mp3</Play>';
            if ($numberOfChildren == 1) {
                if ($male != 0) {
                    $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/jaririn.mp3</Play>';
                } else {
                    $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/jaririan.mp3</Play>';
                }
            } else {
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/jarirain.mp3</Play>';
            }
        } else {
            //the naming ceremony has passed
            //get the difference between the naming ceremony date and today
            $daysToNaming = date_diff(date_create($today), date_create($doc))->days;
            if ($daysToNaming == 1) {
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/yayin-da.mp3</Play>';
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/akayi.mp3</Play>';
            } else {
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/kuma.mp3</Play>';
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/anyi.mp3</Play>';
            }
            $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/radin-sunan.mp3</Play>';
            if ($daysToNaming == 1) {
                //naming was yesterday
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/a-jiya.mp3</Play>';
            } else {
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/a-kwanaki.mp3</Play>';
                $response .= $ivr_functions->callNumbersInWords($daysToNaming, $language);
                //days        
                $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/da-suka-wuce.mp3</Play>';
            }
        }
        $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/zaka-iya-zuwa-kataya.mp3</Play>';
        //name of parent
        $response .= $arrangedNames;
        $response .= '<Play>' . URL('') . '/audio/clips/' . $language . '/da-iyalansa-murnan-haihuwa-lafiya.mp3</Play>';
        $response .= '</Response>';
        return $response;
    }
     /**
     * sends a birth notifications to contacts
     * Ends
     */

    /**
     * sends a registration notifications to parent
     * Begins
     */
    function eddRegistrationNotification(Request $request){
        $callThread = new call_thread();
        $callThread->initialize_call($request);
        $callThread->saveCallBehaviour('OutgoingCall\eddRegistrationNotification');
        //get the audio for this call
        switch ($request->query('Language')) {
            case 'english':
                //$response=$this->composeeddRegistrationNotificationCallEnglish($request->query('ParentID'),$request->query('Mother'),$request->query('Language'));
                break;
            case 'igbo':
                //$response=$this->composeeddRegistrationNotificationCallIgbo($request->query('ParentID'),$request->query('Mother'),$request->query('Language'));
                break;
            case 'hausa':
                $response=$this->composeeddRegistrationNotificationCallHausa($request->query('EddID'),$request->query('ParentID'),$request->query('Mother'),$request->query('Language'));
                break;
            case 'yoruba':
                //$response=$this->composeeddRegistrationNotificationCallYoruba($request->query('ParentID'),$request->query('Mother'),$request->query('Language'));
                break;      
            default:
                //$response=$this->composeeddRegistrationNotificationCallEnglish($request->query('ParentID'),$request->query('Mother'),$request->query('Language'));
            break;
        }
        return response($response)->header('Content-type', 'text/xml');
    }
    function composeeddRegistrationNotificationCallHausa($eddID,$parentID,$mother,$language){
        $ivr_functions = new ivr_functions();
        //get the parent name
       $father=$ivr_functions->fathersName($parentID);
       $arrangedNames=$ivr_functions->callOutGenericName($father);
       $response  = '<?xml version="1.0" encoding="UTF-8"?>';
       $response .= '<Response>';
       //greeting
       $response .= '<Play>' . URL('') . "/audio/clips/$language/salam.mp3</Play>";
       //name of father    
       $response .= $arrangedNames;
       //albarka-matar-ka
       $response .= '<Play>' . URL('') . "/audio/clips/$language/albarka-matar-ka.mp3</Play>";
       //mother's name
       $response .=$ivr_functions->callOutGenericName($ivr_functions->getName($mother));
       $response .= '<Play>' . URL('') . "/audio/clips/$language/edd-registration-notification-remainder-audio.mp3</Play>";
       $response .= '</Response>';
       return $response;
     }
    /**
     * sends a registration notifications to parent
     * Ends
     */
}
