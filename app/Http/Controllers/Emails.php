<?php

namespace App\Http\Controllers;

use App\ivr\cryptography;
use App\Mail\NameAudioUploadMail;
use App\Mail\NoBirthNotifyPhcOfBirth;
use App\Mail\NotifyPhcOfBirth;
use App\Mail\ReminderPhcOfBirth;
use App\Mail\ScheduledCallForBirthReportNotification;
use App\Models\Call_back;
use App\Models\Member;
use App\Models\Name;
use App\Models\Phc;
use App\Models\Phc_no_birth_notification;
use App\Models\Phc_notification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * handles email notifications
 */
class Emails extends Controller
{
    function name_audio_upload_notification(){
        try {
            $names=new Name();
            $names=$names::whereNull('filename');
            if($names->exists()){
                $members=new Member();
                $members=$members::where('email','like','def%')->get();
                $crypto=new cryptography();
                foreach($members as $member){ 
                    Mail::to($crypto->decryptThis($member->email))->send(new NameAudioUploadMail($crypto->decryptThis($member->firstname)));
                }
                return "Update notice sent!";
            }else{
                return "No update found!";
            }
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }

    function scheduled_call_for_birth_report_notification(){
        try {
            $call_backs=new Call_back();
            $call_backs=$call_backs::where('status',0);
            if($call_backs->exists()){
                $members=new Member();
                $members=$members::where('email','like','def%')->get();
                $crypto=new cryptography();
                foreach($members as $member){ 
                    Mail::to($crypto->decryptThis($member->email))->send(new ScheduledCallForBirthReportNotification($crypto->decryptThis($member->firstname)));
                }
                return "Update notice sent!";
            }else{
                return "No update found!";
            }
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }

    function notify_phc_of_birth(){
        try {
            $this_week_start=Carbon::now()->subDays(Carbon::now()->dayOfWeek)->setTime(0,0);
            $phc_notifs=new Phc_notification();
            $phc_notifs=$phc_notifs::with('phc')
                ->whereDate('created_at','>=', $this_week_start)
                ->where('notified',0);
            if($phc_notifs->exists()){
                $phc_notifs=$phc_notifs->get();
                foreach($phc_notifs as $phc_notif){ 
                    Mail::to($phc_notif->email)->send(new NotifyPhcOfBirth($phc_notif->phc->id,$phc_notif->phc->name,$phc_notif->phc->phone,$phc_notif->phc->facility_uid));
                    $phc_notif->notified=1;
                    $phc_notif->save();
                }
                return "Update notice sent!";
            }else{
                return "No update found!";
            }
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }

    function reminder_phc_of_birth(){
        try {
            $last_week_start=Carbon::now()->previous()->subDays(Carbon::now()->previous()->dayOfWeek)->setTime(0,0);
            $phc_notifs=new Phc_notification();
            $phc_notifs=$phc_notifs::with('phc')
                ->whereDate('created_at','>=', $last_week_start)
                ->where('reminded',0);
            if($phc_notifs->exists()){
                $phc_notifs=$phc_notifs->get();
                foreach($phc_notifs as $phc_notif){ 
                    Mail::to($phc_notif->email)->send(new ReminderPhcOfBirth($phc_notif->phc->id,$phc_notif->phc->name,$phc_notif->phc->phone,$phc_notif->phc->facility_uid));
                    $phc_notif->reminded=1;
                    $phc_notif->save();
                }
                return "Update notice sent!";
            }else{
                return "No update found!";
            }
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }
    function no_birth_notify_phc_of_birth(){
        try {
            
            $last_week_start=Carbon::now()->previous()->subDays(Carbon::now()->previous()->dayOfWeek)->setTime(0,0);
            //get the following two models and unite them
            $phc_no_notifs=new Phc_no_birth_notification();
            $phc_no_notifs=$phc_no_notifs::select('phc_id')->whereDate('created_at','>=', $last_week_start);
            $phc_notifs=new Phc_notification();
            $phc_notifs=$phc_notifs::select('phc_id')->whereDate('created_at','>=', $last_week_start)
                ->union($phc_no_notifs);
            //get the records in PHC whose id are not found in the union above
            $phcs=new Phc();
            $phcs=$phcs::whereNotIn('id',$phc_notifs->pluck('phc_id'));
            if($phcs->exists()){
                $phcs=$phcs->get();
                foreach($phcs as $phc){ 
                    if(!is_null($phc->email) and $phc->email!=''){
                        Mail::to($phc->email)->send(new NoBirthNotifyPhcOfBirth($phc->id,$phc->name,$phc->phone,$phc->facility_uid));
                    }
                    $phc_no=new Phc_no_birth_notification();
                    $phc_no->phc_id=$phc->id;
                    $phc_no->email=$phc->email;
                    $phc_no->phone=$phc->phone;
                    $phc_no->notified=1;
                    $phc_no->save();
                }
                return "Update notice sent!";
            }else{
                return "No update found!";
            }
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }
}
