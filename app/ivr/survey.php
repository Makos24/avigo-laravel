<?php

namespace App\ivr;

use Illuminate\Http\Request;
use App\Models\Edd;
use App\Models\Call_back;
use App\Models\Call_behaviour;
class survey
{
    private $language;
    private $currentCallState;
    private     $BirthCounter;
    private     $HasBirthOccurred;
    private     $NumberDelivered;
    private     $eddID;
    private     $parentID;
    private $Question;
    private $date;
    function __construct(String $language)
    {
        $this->language = $language;
    }
    function repeatCallstate(Request $request, $edd){
         //check to know if the caller wants a repeat of a question
         if ($request->has('Digits')) {
            if ($request->input('Digits') == '*') {
                //a repeat of previous quetion is needed, make the previous state the current state
                return $edd->previous_call_state;
            }else{
                return $edd->current_call_state; 
            }
        }else{
            return $edd->current_call_state;
        }
    }
    function mainEntryToSurvey(Request $request, $edd, $sessionId)
    {
        $this->date = date('Y-m-d h:i:s');
        $this->currentCallState = $edd->current_call_state;
        $this->BirthCounter = $edd->birth_counter;
        $this->HasBirthOccurred = $edd->has_birth_occurred;
        $this->NumberDelivered = $edd->number_delivered;
        $this->eddID = $edd->id;
        $this->parentID = $edd->parent_id;
        //check to know if the caller wants a repeat of a question
        /*if ($request->has('Digits')) {
            if ($request->input('Digits') == '*') {
                //a repeat of previous quetion is needed, make the previous state the current state
                $this->currentCallState = $edd->previous_call_state;
            }
        }*/
        $this->currentCallState =$this->repeatCallstate($request, $edd);
        if ($this->currentCallState == 'SessionEnd') {
            // end call
            $response = $this->NoSessionRemainingMessage();
        } else {
            //get the right question and update parameter 
            $this->Question = $this->GetNextCallState();
            $surveyStates = new Edd();
            $surveyState = $surveyStates::where('id', $this->eddID)->first();
            $surveyState->sessionid = $sessionId;
            $surveyState->start_time = $this->date;
            $surveyState->end_time = $this->date;
            $surveyState->previous_call_state = $this->currentCallState;
            $surveyState->current_call_state = $this->Question;
            $surveyState->was_last_input_correct = 1;
            if ($surveyState->save()) {
                // Compose the response
                $response = $this->GetCurrentQuestionToAsk();
            } else {
                // Compose the response
                $response = $this->ErrorMessage();
            }
        }
        return $response;
    }
    function composeQuestionForMothersNameSelection(Request $request, $edd, $callerNumber)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $namesIncomplete = false;
        //check if any of the name audios is not yet recorded
        foreach ($edd as $key => $value) {
            if ($value['name']['filename'] == '') {
                $namesIncomplete = true;
                break;
            }
        }
        if ($namesIncomplete == false) {
            $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php?MothersName=Yes&amp;ParentID=' . $value['parent_id'] . '" method="POST">';
            //$response .= '<Say voice="alice">Albarka! Thank you for contacting us. Please select the woman whose delivery you want to report.</Say>';
            $response .= "<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/$this->language/select-mother-prefix.mp3</Play>";
            //get the mothers' names and compose the message
            $count = 0;
            // if all the names for mothers in this family are fully recorded
            foreach ($edd as $key => $value) {
                //$response .= '<Say voice="alice">For</Say>';
                $response .= "<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/$this->language/for.mp3</Play>";
                $response .= '<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/names/' . $value['name']['filename'] . '</Play>';
                //$response .= '<Say voice="alice">Press</Say>';
                $response .= "<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/$this->language/press.mp3</Play>";
                $response .= $this->callNumbersInWords(++$count);
            }
         $response .= '</Gather>';
        } else {
            //tell the parent that a call would be routed to him later to manually get the content of the survey
            //$response .= '<Say voice="alice">Albarka! Thank you for contacting us. Our team would reach you in the shortest time to get your report through a call.</Say>';
         $response .= "<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/$this->language/call-back.mp3</Play>";
            return $this->scheduleACall($callerNumber);
            //send email notification of this call scheduled-call-for-birth-report
            $this->sendEmailForCalls();
        }
        $response .= '</Response>';
        return $response;
    }
    function sendEmailForCalls()
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://avigohealth.com/twilio/outbound-voice-call-callback-uri/crontabs/scheduled-call-for-birth-report.php",
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
     /*function composeQuestionForMothersNameSelectionEnglish($resp)
    {
        $rows = mysqli_fetch_assoc($resp);
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php?MothersName=Yes&amp;ParentID=' . $rows['ParentID'] . '" method="POST">';
        $response .= '<Say voice="alice">Albarka! Thank you for contacting us. Please select the woman whose delivery you want to report.</Say>';
        //get the mothers' names and compose the message
        $count = 0;
        mysqli_data_seek($resp, 0);
        while ($rows = mysqli_fetch_assoc($resp)) {
            $response .= '<Say voice="alice">For</Say>';
            $response .= '<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/names/' . $rows['Filename'] . '</Play>';
            $response .= '<Say voice="alice">Press</Say>';
            $response .= callNumbersInWords(++$count, 'english');
        }
        $response .= '</Gather>';
        //$response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question-none-error-input.php</Redirect>';
        $response .= '</Response>';
        return $response;
    }*/
    function scheduleACall($phone)
    {
        $callBacks=new Call_back();
        $callBack=$callBacks::where('phone',$phone);
        if ($callBack->exists()) {
            $callBack=$callBack->first();
            $callBack->status=0;
            $callBack->save();
        } else {
            $callBacks->phone=$phone;
            $callBacks->save();
        }
    }
    function setCallBehaviour($sessionId, $direction, $callSessionState, $date, $callerNumber, $EddID, $ParentID, $uri = '')
    {
        $calls = new Call_behaviour();
        $call=$calls::where('sessionid', $sessionId)
                        ->where('phone',$callerNumber)
                        ->where('edd_id',$EddID)
                        ->where('parent_id',$ParentID);
		if ($call->exists()) {
			$call = $call->first();
			$call->duration = 0;
			$call->end_date = $date;
			$call->final_call_status = $callSessionState;
			$call->save();
		} else {
			$calls->sessionid = $sessionId;
			$calls->phone = $callerNumber;
			$calls->start_date = $date;
			$calls->end_date = $date;
			$calls->uri = $uri;
			$calls->call_direction = $direction;
			$calls->initial_call_status = $callSessionState;
			$calls->parent_id = $ParentID;
			$calls->edd_id = $EddID;
			$calls->save();
		}
    }
    /*function RepeatQuestion($currentCallState)
    {
        switch ($currentCallState) {
            case 'None':
                return 'Question1';
                break;
            case 'Question1':
                return 'Question2';
                break;
            case 'Question2':
                if ($BirthCounter == '1') {
                    return 'Question3';
                } else {
                    return 'Question3A';
                }
                break;
            case 'Question3A':
                $BirthCounter = $BirthCounter - 1;
                if ($BirthCounter == 0) {
                    return 'Question4';
                } else {
                    return 'Question3B';
                }
                break;
            case 'Question3B':
                $BirthCounter = $BirthCounter - 1;
                if ($BirthCounter == 0) {
                    return 'Question4';
                } else {
                    return 'Question3C';
                }
                break;
            case 'Question3C':
                $BirthCounter = $BirthCounter - 1;
                if ($BirthCounter == 0) {
                    return 'Question4';
                } else {
                    return 'Question3D';
                }
                break;
            case 'Question3D':
                $BirthCounter = $BirthCounter - 1;
                if ($BirthCounter == 0) {
                    return 'Question4';
                } else {
                    return 'Question3E';
                }
                break;
            case 'Question3E':
                $BirthCounter = $BirthCounter - 1;
                if ($BirthCounter == 0) {
                    return 'Question4';
                } else {
                    return 'Question3F';
                }
                break;
            case 'Question3F':
                $BirthCounter = $BirthCounter - 1;
                if ($BirthCounter == 0) {
                    return 'Question4';
                } else {
                    return 'Question3G';
                }
                break;
            case 'Question3G':
                $BirthCounter = $BirthCounter - 1;
                if ($BirthCounter == 0) {
                    return 'Question4';
                } else {
                    return 'Question3H';
                }
                break;
            case 'Question3H':
                $BirthCounter = $BirthCounter - 1;
                if ($BirthCounter == 0) {
                    return 'Question4';
                } else {
                    return 'Question4';
                }
                break;
            case 'Question3':
                return 'Question4';
                break;
            case 'Question4':
                return 'Question5';
                break;
            case 'Question5':
                return 'Question6';
                break;
            case 'Question6':
                return 'QuestionEnd';
                break;
        }
    }*/
    function GetNextCallState()
    {
        switch ($this->currentCallState) {
            case 'None':
                return 'Question1';
                break;
            case 'Question1':
                if ($this->HasBirthOccurred == '1') {
                    return 'Question2';
                } else {
                    return 'Question1';
                }
                break;
            case 'Question2':
                if ($this->NumberDelivered == '1') {
                    return 'Question3';
                } else {
                    return 'Question2C';
                }
                break;
            case 'Question2C':
                return 'Question3';
                break;
            case 'Question3':
                return 'Question4';
                break;
            case 'Question4':
                return 'Question5';
                break;
            case 'Question5':
                return 'QuestionEnd';
                break;
            case 'QuestionEnd':
                return 'SessionEnd';
                break;
        }
    }

    function GetCurrentQuestionToAsk()
    {
        switch ($this->currentCallState) {
            case 'None':
                return $this->QuestionNone();
                break;
            case 'Question1':
                if ($this->HasBirthOccurred == '1') {
                    return $this->Question1();
                } else {
                    //return Question1Negative();
                    return $this->QuestionNone();
                }
                break;
            case 'Question2':
                if ($this->NumberDelivered == '1') {
                    return $this->Question2A();
                } else {
                    return $this->Question2B();
                }
                break;
            case 'Question2C':
                return $this->Question2C();
                break;
            case 'Question3':
                //return Question3();
                if ($this->NumberDelivered == 1) {
                    return $this->Question3();
                } else {
                    return $this->Question3MoreThanOneChild();
                }
                break;
            case 'Question4':
                //return Question4();
                if ($this->NumberDelivered == 1) {
                    return $this->Question4();
                } else {
                    return $this->Question4MoreThanOneChild();
                }
                break;
            case 'Question5':
                return $this->Question5();
                break;
            case 'QuestionEnd':
                return $this->Question6End();
                break;
        }
    }

    function NoSessionRemainingMessage()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Say voice="alice">You have fully reported! Thank you</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/report-in-full-message.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }/*
    function NoParentDetailMessage($language)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Say voice="alice">Sorry, parent detail not found! Thank you</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/no-parent-detail-message.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }
    function NoSurveyFoundMessage($language)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Say voice="alice">Sorry, no survey found! Thank you</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/no-survey-found.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }
    function IncorrectInputEndCall($language)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Say voice="alice">Oops! Input not correct. Please try again. Thank you</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/incorrect-input-end-call.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }*/
    function UnAuthorisedUserMessage()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Say voice="alice">Oops! We discovered you are not registered on the Avigo Health platform. Thank you</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/unauthorised-user-message.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }
    function ErrorMessage()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Say voice="alice">Oops! Just encountered an error. Please retry again</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/error-message.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }
    function EndCallAndAskaRecall()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        //$response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/error-message.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }
    function QuestionNone()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Gather finishOnKey="#">';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php" method="POST">';
        //$response .= '<Say voice="alice">Thank you for calling Albarka. Share your good news. Has the birth occurred? If Yes, Press 1. If No, Press 2. To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-none.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question-none.php?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function QuestionNoneErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Gather finishOnKey="#">';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php?error=input" method="POST">';
        //$response .= '<Say voice="alice">The input you supplied for the last question is not correct. If birth has occurred, Press 1. Or Press 2 if birth has not occurred.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-none-error-input.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question-none.php?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question1()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php" method="POST">';
        //$response .= '<Say voice="alice">Enter the number of children born. To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-1.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question1.php?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question1ErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php?error=input" method="POST">';
        //$response .= '<Say voice="alice">The input you supplied for the last question is not correct. Enter the number of children born. To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-1.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question1.php?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question1Negative()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Say voice="alice">Thank you for calling Albarka, feel free to reach us again when your wife gives birth.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-1-negative.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }
    function Question2A()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php" method="POST">';
        //$response .= '<Say voice="alice">What is the gender of the child? Press 1 for female or 2 for male. To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-2-A.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question2A.php?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question2AErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php?error=input" method="POST">';
        //$response .= '<Say voice="alice">The input you supplied for the last question is not correct. If gender of the child is female Press 1 or press 2 for male. To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-2-A.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question2A.php?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question2B()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php" method="POST">';
        //$response .= '<Say voice="alice">How many of these children are boys? To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-2-B.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question2B.php?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question2BErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php?error=input" method="POST">';
        //$response .= '<Say voice="alice">The input you supplied for the last question is not correct. How many of these children are boys? To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-2-B.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question2B.php?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question2C()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php" method="POST">';
        //$response .= '<Say voice="alice">How many of these children are girls? To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-2-C.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question-2-C.php?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question2CErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php?error=input" method="POST">';
        //$response .= '<Say voice="alice">The input you supplied for the last question is not correct. How many of these children are girls? To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-2-C.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question-2-C.php?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question3()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php" method="POST">';
        //$response .= '<Say voice="alice">When was your child born? Press 0 for today, 1 for yesterday, 3 for three days ago, 7 for one week ago. To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-3.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question3.php?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question3ErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php?error=input" method="POST">';
        //$response .= '<Say voice="alice">The input you supplied for the last question is not correct. Values can only be from 0 to 7. If your child was born today Press 0, yesterday press 1, three days ago press 4, one week ago press 7. To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-3.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question3.php?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question3MoreThanOneChild()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php" method="POST">';
        //$response .= '<Say voice="alice">When were your children born? Press 0 for today, 1 for yesterday, 3 for three days ago, 7 for one week ago. To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-3-more-than-one-child.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question3-more-than-one-child.php?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question3MoreThanOneChildErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php?error=input" method="POST">';
        //$response .= '<Say voice="alice">The input you supplied for the last question is not correct. Values can only be from 0 to 7. If your children were born today Press 0, yesterday press 1, three days ago press 3, one week ago press 7. To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/hausa/error-prefix.mp3</Play>";
        $response .= "<Play>https://avigohealth.com/ivr-audio/hausa/question-3-more-than-one-child.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question3-more-than-one-child.php?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question4()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php" method="POST">';
        //$response .= '<Say voice="alice"> When is the naming ceremony of the child? Press 0 for today, 1 for tomorrow, 3 for three days from now, 7 for one week from now. To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-4.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question4.php?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question4ErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php?error=input" method="POST">';
        //$response .= '<Say voice="alice"> The input you supplied for the last question is not correct. Values can only be from 0 to 7. If the naming ceremony is today Press 0, tomorrow press 1, three days from now press 3, one week from now press 7. To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-4.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question4.php?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question4MoreThanOneChild()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php" method="POST">';
        //$response .= '<Say voice="alice"> When is the naming ceremony of the children? Press 0 for today, 1 for tomorrow, 3 for three days from now, 7 for one week from now. To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-4-more-than-one-child.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question4-more-than-one-child.php?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question4MoreThanOneChildErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php?error=input" method="POST">';
        //$response .= '<Say voice="alice"> The input you supplied for the last question is not correct. Values can only be from 0 to 7. If the naming ceremony is today Press 0, tomorrow press 1, three days from now press 3, one week from now press 7. To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-4-more-than-one-child.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question4-more-than-one-child.php?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question5()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php" method="POST">';
        // $response .= '<Say voice="alice">Do you want us to share news with your contacts? Press 1 for Yes or 0 for No. To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-5.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question5.php?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question5ErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="https://avigohealth.com/twilio/inbound-voice-call-callback-uri/index.php?error=input" method="POST">';
        //$response .= '<Say voice="alice">The input you supplied for the last question is not correct. If you want us to share your news with your contacts? Press 1 for Yes or 0 for No. To listen to the question again press star.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-5.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">https://avigohealth.com/twilio/inbound-voice-call-callback-uri/redirects/question5.php?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question6End()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Say voice="alice">Thanks for your time.</Say>';
        $response .= "<Play>https://avigohealth.com/ivr-audio/$this->language/question-6-end.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }







    /*function GetPreviousCallState($previousCallState)
    {
        switch ($previousCallState) {
            case 'None':
                return 'None';
                break;
            case 'Question1':
                return 'None';
                break;
            case 'Question2':
                return 'Question1';
                break;
            case 'Question3':
                return 'Question2';
                break;
            case 'Question3A':
                return 'Question2';
                break;
            case 'Question3B':
                return 'Question3A';
                break;
            case 'Question3C':
                return 'Question3B';
                break;
            case 'Question3D':
                return 'Question3C';
                break;
            case 'Question3E':
                return 'Question3D';
                break;
            case 'Question3F':
                return 'Question3E';
                break;
            case 'Question3G':
                return 'Question3F';
                break;
            case 'Question3H':
                return 'Question3G';
                break;
            case 'Question4':
                $BirthCounter = $BirthCounter - 1;
                if ($BirthCounter == 0) {
                    return 'Question3';
                } else {
                    return 'Question3A';
                }
                break;
            case 'Question5':
                return 'Question4';
                break;
            case 'Question6':
                return 'Question5';
                break;
            case 'QuestionEnd':
                return 'Question6';
                break;
        }
    }
    function CallStateKeeperErrorInput($dbc, $previousCallState, $EddID, $WasLastInputCorrect)
    {
        $query = "UPDATE `edd` SET `WasLastInputCorrect`='$WasLastInputCorrect',`PreviousCallState`='" . GetPreviousCallState($previousCallState) . "',`CurrentCallState`='$previousCallState' WHERE `EddID`='$EddID'";
        if (mysqli_query($dbc, $query)) {
        }
    }*/
    function callNumbersInWords($number)
    {
        switch ($number) {
            case 1:
                $response = "<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/$this->language/1-$this->language.mp3</Play>";
                break;
            case 2:
                $response = "<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/$this->language/2-$this->language.mp3</Play>";
                break;
            case 3:
                $response = "<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/$this->language/3-$this->language.mp3</Play>";
                break;
            case 4:
                $response = "<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/$this->language/4-$this->language.mp3</Play>";
                break;
            case 5:
                $response = "<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/$this->language/5-$this->language.mp3</Play>";
                break;
            case 6:
                $response = "<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/$this->language/6-$this->language.mp3</Play>";
                break;
            case 7:
                $response = "<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/$this->language/7-$this->language.mp3</Play>";
                break;
            case 8:
                $response = "<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/$this->language/8-$this->language.mp3</Play>";
                break;
            case 9:
                $response = "<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/$this->language/9-$this->language.mp3</Play>";
                break;
        }
        return $response;
    }
/*
    function checkTwins($numberOfChildren, $language)
    {
        if ($numberOfChildren > 2) {
            //these are not twins
            $response = "<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/$this->language/all-$language.mp3</Play>";
        } else {
            //these are twins
            $response = "<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/$this->language/both-$language.mp3</Play>";
        }
        return $response;
    }

    function parentLanguage($dbc, $parentID)
    {
        //get the language of this parent
        $query = "SELECT `languages`.`Name` FROM `languages` INNER JOIN (`parent_details`) ON (`languages`.`LangID`=`parent_details`.`LangID`) WHERE `parent_details`.`ParentID`='$parentID'";
        $response = mysqli_query($dbc, $query);
        if ($rows = mysqli_fetch_assoc($response)) {
            if (!is_bool($response)) {
                mysqli_free_result($response);
            }
            return $rows['Name'];
        } else {
            return 'hausa';
        }
    }
    function parentLanguageFromPhone($dbc, $callerNumber)
    {
        //get the language of this parent
        $query = "SELECT `languages`.`Name` FROM `languages` INNER JOIN (`parent_details`) ON (`languages`.`LangID`=`parent_details`.`LangID`) WHERE `parent_details`.`Phone`='$callerNumber'";
        $response = mysqli_query($dbc, $query);
        if ($rows = mysqli_fetch_assoc($response)) {
            if (!is_bool($response)) {
                mysqli_free_result($response);
            }
            return $rows['Name'];
        } else {
            return 'hausa';
        }
    }
    function callOutName($dbc, $name, $language)
    {
        $nameArray = explode(' ', $name);
        $arrangedNames = ''; //holds names in the correct order they appear in the parent_details table
        if (is_array($nameArray)) {
            $count = count($nameArray);
            //get individual name file and append to twiML response
            while ($count > 0) {
                $count -= 1;
                $query = "SELECT Filename FROM names WHERE Name='" . strtolower($nameArray[$count]) . "'";
                $Callresponse = mysqli_query($dbc, $query);
                if ($rows = mysqli_fetch_assoc($Callresponse)) {
                    if (!is_bool($Callresponse)) {
                        mysqli_free_result($Callresponse);
                    }
                    if ($rows['Filename'] != '') {
                        //this comes when an audio file is available for this name
                        $arrangedNames = '<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/names/' . $rows['Filename'] . '</Play>' . $arrangedNames;
                    }
                }
            }
        }
        if ($arrangedNames == '') {
            //this comes in if parent name audio has not been recorded
            $arrangedNames = "<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/$this->language/a-friend-of-yours.mp3</Play>";
        }
        return $arrangedNames;
    }
    function callOutGenericName($dbc, $name)
    {
        $nameArray = explode(' ', $name);
        $arrangedNames = ''; //holds names in the correct order they appear in the parent_details table
        if (is_array($nameArray)) {
            $count = count($nameArray);
            //get individual name file and append to twiML response
            while ($count > 0) {
                $count -= 1;
                $query = "SELECT Filename FROM names WHERE Name='" . mysqli_real_escape_string($dbc, strtolower($nameArray[$count])) . "'";
                $Callresponse = mysqli_query($dbc, $query);
                if ($rows = mysqli_fetch_assoc($Callresponse)) {
                    if (!is_bool($Callresponse)) {
                        mysqli_free_result($Callresponse);
                    }
                    if ($rows['Filename'] != '') {
                        //this comes when an audio file is available for this name
                        $arrangedNames = '<Play>https://avigohealth.com/twilio/outbound-voice-call-callback-uri/audio-component-clips/names/' . $rows['Filename'] . '</Play>' . $arrangedNames;
                    }
                }
            }
        }
        return $arrangedNames;
    }
    function fathersName($dbc, $parentID)
    {
        $name = '';
        $query = "SELECT Name FROM parent_details WHERE ParentID='$parentID'";
        $Callresponse = mysqli_query($dbc, $query);
        if ($rows = mysqli_fetch_assoc($Callresponse)) {
            if (!is_bool($Callresponse)) {
                mysqli_free_result($Callresponse);
            }
            $name = $rows['Name'];
        }
        return $name;
    }
    function getName($dbc, $nameIndex)
    {
        $name = '';
        $query = "SELECT Name FROM names WHERE SN='$nameIndex'";
        $Callresponse = mysqli_query($dbc, $query);
        if ($rows = mysqli_fetch_assoc($Callresponse)) {
            if (!is_bool($Callresponse)) {
                mysqli_free_result($Callresponse);
            }
            $name = $rows['Name'];
        }
        return $name;
    }
    function getNameIndex($dbc, $name)
    {
        $nameIndex = '';
        $query = "SELECT SN FROM names WHERE Name='$name'";
        $Callresponse = mysqli_query($dbc, $query);
        if ($rows = mysqli_fetch_assoc($Callresponse)) {
            if (!is_bool($Callresponse)) {
                mysqli_free_result($Callresponse);
            }
            $nameIndex = $rows['SN'];
        }
        return $nameIndex;
    }

    function saveCallBehaviour($dbc, $sessionId, $duration, $date, $callSessionState, $callerNumber, $direction, $uri = "")
    {
        $callquery = "SELECT SN FROM call_behaviour_tracker WHERE `SessionID`='$sessionId'";
        $callresponse = mysqli_query($dbc, $callquery);
        if (mysqli_fetch_assoc($callresponse)) {
            if (!is_bool($callresponse)) {
                mysqli_free_result($callresponse);
            }
            $callquery = "UPDATE `call_behaviour_tracker` SET `Duration`='$duration',`EndDate`='$date',`FinalCallStatus`='$callSessionState' WHERE `Phone`='$callerNumber' AND `SessionID`='$sessionId'";
            if (mysqli_query($dbc, $callquery)) {
            }
        } else {
            $callquery = "INSERT INTO `call_behaviour_tracker` (`Phone`,`SessionID`,`StartDate`,`EndDate`,`InitialCallStatus`,`CallDirection`,`URI`) VALUES ('$callerNumber','$sessionId','$date','$date','$callSessionState','$direction','$uri')";
            if (mysqli_query($dbc, $callquery)) {
            }
        }
    }

    function inputTrackerPerQuestion($dbc, $sessionId, $parentID, $eddID, $question)
    {
        $query = "SELECT `Times` FROM `user_input_tracker` WHERE `SessionID`='$sessionId' AND `ParentID`='$parentID' AND `EddID`='$eddID' AND `Question`='$question'";
        $response = mysqli_query($dbc, $query);
        if ($rows = mysqli_fetch_assoc($response)) {
            if (!is_bool($response)) {
                mysqli_free_result($response);
            }
            $times = $rows['Times'] + 1;
            $query = "UPDATE `user_input_tracker` SET `Times`='$times' WHERE `SessionID`='$sessionId' AND `ParentID`='$parentID' AND `EddID`='$eddID' AND `Question`='$question'";
            if (mysqli_query($dbc, $uery)) {
            }
            if ($times = 3) {
                // end call and ask the caller to retry
                return 1;
            } else {
                //repeat the call for the user to hear clearly
                return 0;
            }
        } else {
            $query = "INSERT INTO `user_input_tracker` (`SessionID`,`ParentID`,`EddID`,`Question`) VALUES ('$sessionId','$parentID','$eddID','$question')";
            if (mysqli_query($dbc, $query)) {
            }
            //allow program to flow normally 
            return 0;
        }
    }
    function cleanPhone($number)
    {
        if (substr($number, 0, 1) == "+") {
            return str_replace(' ', '', $number);
        } elseif (substr($number, 0, 1) == "2") {
            return "+" . str_replace(' ', '', $number);
        } elseif (substr($number, 0, 1) == "0") {
            $p = ltrim($number, '0');
            return '+234' . str_replace(' ', '', $p);
        }
    }
    function getDatePast($daysFromNow)
    {
        $doc = date('Y-m-d');
        switch ($daysFromNow) {
            case '0':
                $doc = date('Y-m-d');
                break;
            case '1':
                $doc = dateGetterFromNumberOfDays('-1');
                break;
            case '2':
                $doc = dateGetterFromNumberOfDays('-2');
                break;
            case '3':
                $doc = dateGetterFromNumberOfDays('-3');
                break;
            case '4':
                $doc = dateGetterFromNumberOfDays('-4');
                break;
            case '5':
                $doc = dateGetterFromNumberOfDays('-5');
                break;
            case '6':
                $doc = dateGetterFromNumberOfDays('-6');
                break;
            case '7':
                $doc = dateGetterFromNumberOfDays('-7');
                break;
            default:
                $doc = dateGetterFromNumberOfDays('-' . $daysFromNow);
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
                $doc = dateGetterFromNumberOfDays('+1');
                break;
            case '2':
                $doc = dateGetterFromNumberOfDays('+2');
                break;
            case '3':
                $doc = dateGetterFromNumberOfDays('+3');
                break;
            case '4':
                $doc = dateGetterFromNumberOfDays('+4');
                break;
            case '5':
                $doc = dateGetterFromNumberOfDays('+5');
                break;
            case '6':
                $doc = dateGetterFromNumberOfDays('+6');
                break;
            case '7':
                $doc = dateGetterFromNumberOfDays('+7');
                break;
            default:
                $doc = dateGetterFromNumberOfDays('+' . $daysFromNow);
                break;
        }
        return $doc;
    }
    function dateGetterFromNumberOfDays($numberOfDaysByDirection)
    {
        return date('Y-m-d', strtotime(date('Y-m-d') . $numberOfDaysByDirection . ' day'));
    }
    function ensureNumberIsFormattedCorrectly($phoneNumber)
    {
        $findme = '234';
        $pos = stripos($phoneNumber, $findme);
        if ($pos !== false) {
            //if 234 is in the phone number it is likely a Nigerian number
            if ($pos != 0) {
                //if 124 is not the first set of characters in the phone number it is likely a Nigerian Number
                $phoneNumberCopy = '+234' . substr($phoneNumber, $pos + 3); //return all characters to the right of 234 in the phone number after that prepend +234
                if (strlen($phoneNumberCopy) == 14) {
                    //if the reconstructed phone number is 14 characters long, then it is a Nigerian number in international format
                    $phoneNumber = $phoneNumberCopy;
                }
            } else {
                //if 234 is the first set of characters in the phone number it is likely a Nigerian Number
                if (strlen($phoneNumber) == 13) {
                    //if the length of the phone is 13 then it is likely a Nigerian Number 
                    $phoneNumber = '+' . $phoneNumber;
                }
            }
        } else {
            //080
            $phoneNumber = '+234' . ltrim($phoneNumber, '0');
        }
        return $phoneNumber;
    }
    //update health facilities notifications
    function updatePhcNotification($dbc, $FacID, $phcPhone, $phcEmail, $NumberDelivered)
    {
        $query = "SELECT `SN` FROM `phc_notification` WHERE `FacID`='$FacID' AND YEARWEEK(CreatedAt)=YEARWEEK(CURDATE())";
        $resp = mysqli_query($dbc, $query);
        if ($prows = mysqli_fetch_assoc($resp)) {
            if (!is_bool($resp)) {
                mysqli_free_result($resp);
            }
            $query = "UPDATE `phc_notification` SET `NewBirths`=(NewBirths+$NumberDelivered) WHERE `SN`='" . $prows['SN'] . "'";
            if (mysqli_query($dbc, $query) == false) {
            }
        } else {
            $query = "INSERT INTO `phc_notification` (`FacID`,`Phone`,`Email`,`NewBirths`) VALUES ('$FacID','$phcPhone','$phcEmail','$NumberDelivered')";
            if (mysqli_query($dbc, $query) == false) {
            }
        }
    }
    //add contacts to notification table
    function setUpContactNotification($dbc, $parentID, $EddID)
    {
        $query = "SELECT ContactID,Phone FROM birth_contacts WHERE ParentID='$parentID'";
        $Presponse = mysqli_query($dbc, $query);
        while ($PhoneRows = mysqli_fetch_assoc($Presponse)) {
            $NQuery = "SELECT SN FROM birth_notifications WHERE EddID='$EddID' AND ContactID='" . $PhoneRows['ContactID'] . "' AND Phone='" . $PhoneRows['Phone'] . "'";
            $NResponse = mysqli_query($dbc, $NQuery);
            if (mysqli_fetch_assoc($NResponse)) {
                if (!is_bool($NResponse)) {
                    mysqli_free_result($NResponse);
                }
                $query = "UPDATE `birth_notifications` SET Notified='0' WHERE EddID='$EddID' AND ContactID='" . $PhoneRows['ContactID'] . "' AND Phone='" . $PhoneRows['Phone'] . "'";
                if (mysqli_query($dbc, $query) == false) {
                }
            } else {
                $query = "INSERT INTO `birth_notifications` (`ContactID`,`EddID`,`Phone`) VALUES ('" . $PhoneRows['ContactID'] . "','$EddID','" . $PhoneRows['Phone'] . "')";
                if (mysqli_query($dbc, $query) == false) {
                }
            }
        }
        if (!is_bool($Presponse)) {
            mysqli_free_result($Presponse);
        }
    }*/
}
