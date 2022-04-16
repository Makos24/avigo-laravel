<?php
namespace App\ivr;
use App\Models\Parent_detail;
use App\Models\Name;
use App\Models\Phc_notification;
use App\Models\Birth_contact;
use App\Models\Birth_notification;
class ivr_functions{
    function __construct()
	{
	}
    function fathersName($parentID)
    {
        //get the name of this parent
		$parent = new Parent_detail();
		if ($parent::where('id', $parentID)->exists()) {
			return $parent::where('id', $parentID)->first()->name;
		} else {
			return '';
		}
    }
    function getName($nameIndex)
    {
       //get the name of this index
        $names = new Name();
		if ($names::where('id', $nameIndex)->exists()) {
			return $names::where('id', $nameIndex)->first()->name;
		} else {
			return '';
		}
    }
    function getNameIndex($name)
    {
        //get the index of this name
        $names = new Name();
		if ($names::where('name', $name)->exists()) {
			return $names::where('name', $name)->first()->id;
		} else {
			return '';
		}
    }
    function callOutName($name, $language)
    {
        $nameArray = explode(' ', $name);
        $arrangedNames = ''; //holds names in the correct order they appear in the parent_details table
        if (is_array($nameArray)) {
            $count = count($nameArray);
            //get individual name file and append to twiML response
            while ($count > 0) {
                $count -= 1;
                $names = new Name();
                if ($names::where('name', strtolower($nameArray[$count]))->exists()) {
                    $file=$names::where('name',strtolower($nameArray[$count]))->first()->filename;
                    if ($file != '') {
                        //this comes when an audio file is available for this name
                        $arrangedNames = '<Play>'.URL('').'/audio/clips/names/' . $file . '</Play>' . $arrangedNames;
                    }
                } 
            }
        }
        if ($arrangedNames == '') {
            //this comes in if parent name audio has not been recorded
            $arrangedNames = "<Play>".URL('')."/audio/clips/$language/a-friend-of-yours.mp3</Play>";
        }
        return $arrangedNames;
    }
    function callOutGenericName($name)
    {
        $nameArray = explode(' ', $name);
        $arrangedNames = ''; //holds names in the correct order they appear in the parent_details table
        if (is_array($nameArray)) {
            $count = count($nameArray);
            //get individual name file and append to twiML response
            while ($count > 0) {
                $count -= 1;
                $names = new Name();
                if ($names::where('name', strtolower($nameArray[$count]))->exists()) {
                    $file=$names::where('name',strtolower($nameArray[$count]))->first()->filename;
                    if ($file != '') {
                        //this comes when an audio file is available for this name
                        $arrangedNames = '<Play>'.URL('').'/audio/clips/names/' . $file . '</Play>' . $arrangedNames;
                    }
                } 

            }
        }
        return $arrangedNames;
    }
    function callNumbersInWords($number,$language)
    {
        switch ($number) {
            case 1:
                $response = "<Play>".URL('')."/audio/clips/$language/1-$language.mp3</Play>";
                break;
            case 2:
                $response = "<Play>".URL('')."/audio/clips/$language/2-$language.mp3</Play>";
                break;
            case 3:
                $response = "<Play>".URL('')."/audio/clips/$language/3-$language.mp3</Play>";
                break;
            case 4:
                $response = "<Play>".URL('')."/audio/clips/$language/4-$language.mp3</Play>";
                break;
            case 5:
                $response = "<Play>".URL('')."/audio/clips/$language/5-$language.mp3</Play>";
                break;
            case 6:
                $response = "<Play>".URL('')."/audio/clips/$language/6-$language.mp3</Play>";
                break;
            case 7:
                $response = "<Play>".URL('')."/audio/clips/$language/7-$language.mp3</Play>";
                break;
            case 8:
                $response = "<Play>".URL('')."/audio/clips/$language/8-$language.mp3</Play>";
                break;
            case 9:
                $response = "<Play>".URL('')."/audio/clips/$language/9-$language.mp3</Play>";
                break;
        }
        return $response;
    }

    function checkTwins($numberOfChildren, $language)
    {
        if ($numberOfChildren > 2) {
            //these are not twins
            $response = "<Play>".URL('')."/audio/clips/$this->language/all-$language.mp3</Play>";
        } else {
            //these are twins
            $response = "<Play>".URL('')."/audio/clips/$this->language/both-$language.mp3</Play>";
        }
        return $response;
    }

    function getDatePast($daysFromNow)
    {
        $doc = date('Y-m-d');
        switch ($daysFromNow) {
            case '0':
                $doc = date('Y-m-d');
                break;
            case '1':
                $doc = $this->dateGetterFromNumberOfDays('-1');
                break;
            case '2':
                $doc = $this->dateGetterFromNumberOfDays('-2');
                break;
            case '3':
                $doc = $this->dateGetterFromNumberOfDays('-3');
                break;
            case '4':
                $doc = $this->dateGetterFromNumberOfDays('-4');
                break;
            case '5':
                $doc = $this->dateGetterFromNumberOfDays('-5');
                break;
            case '6':
                $doc = $this->dateGetterFromNumberOfDays('-6');
                break;
            case '7':
                $doc = $this->dateGetterFromNumberOfDays('-7');
                break;
            default:
                $doc = $this->dateGetterFromNumberOfDays('-' . $daysFromNow);
                break;
        }
        return $doc;
    }
    function getDateAhead($daysFromNow)
    {
        $doc = date('Y-m-d');
        switch ($daysFromNow) {
            case '0':
                $doc = date('Y-m-d');
                break;
            case '1':
                $doc = $this->dateGetterFromNumberOfDays('+1');
                break;
            case '2':
                $doc = $this->dateGetterFromNumberOfDays('+2');
                break;
            case '3':
                $doc = $this->dateGetterFromNumberOfDays('+3');
                break;
            case '4':
                $doc = $this->dateGetterFromNumberOfDays('+4');
                break;
            case '5':
                $doc = $this->dateGetterFromNumberOfDays('+5');
                break;
            case '6':
                $doc = $this->dateGetterFromNumberOfDays('+6');
                break;
            case '7':
                $doc = $this->dateGetterFromNumberOfDays('+7');
                break;
            default:
                $doc = $this->dateGetterFromNumberOfDays('+' . $daysFromNow);
                break;
        }
        return $doc;
    }
    function dateGetterFromNumberOfDays($numberOfDaysByDirection)
    {
        return date('Y-m-d', strtotime(date('Y-m-d') . $numberOfDaysByDirection . ' day'));
    }

    function updatePhcNotification($FacID, $phcPhone, $phcEmail, $NumberDelivered)
    {
        $phc_notifications = new Phc_notification();
        $phc_notification = $phc_notifications::where('id', $FacID)
                ->whereDate('created_at','>',$this->dateGetterFromNumberOfDays(-7))
                ->whereDate('created_at','>',date('Y-m-d'));
        if ($phc_notification->exists()) {
            $phc_notification->new_births +=$NumberDelivered;
            $phc_notification->save();
        } else {
            $phc_notifications->phc_id =$FacID;
            $phc_notifications->new_births =$NumberDelivered;
            $phc_notifications->phone =$phcPhone;
            $phc_notifications->email =$phcEmail;
            $phc_notifications->save();
        }
    }

    //add contacts to notification table
    function setUpContactNotification($parentID, $EddID)
    {
       // $query = "SELECT ContactID,Phone FROM birth_contacts WHERE ParentID='$parentID'";
        $Birth_contacts = new Birth_contact();
        $Birth_contact = $Birth_contacts::where('parent_id', $parentID);
        if ($Birth_contact->exists()) {
            $Birth_contact=  $Birth_contact->get();
            foreach ($Birth_contact as $key => $contact) { 
                $birth_notifications=new Birth_notification();
                $birth_notification = $birth_notifications::where('edd_id', $EddID)
                                        ->where('birth_contact_id',$contact['id']);
                if ($birth_notification->exists()) {
                    $birth_notification=$birth_notification->get()->first();
                    $birth_notification->notified='0';
                    $birth_notification->save();
                } else {
                    $birth_notifications->birth_contact_id=$contact['id'];
                    $birth_notifications->edd_id=$EddID;
                    $birth_notifications->phone=$contact['phone'];
                    $birth_notifications->save();
                }
            }
        }
    }
    function sendEmailForCalls()
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => URL('')."/twilio/outbound-voice-call-callback-uri/crontabs/scheduled-call-for-birth-report.php",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Cache-Control: no-cache",
            ),
        ));
        $request = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
    }
}


?>