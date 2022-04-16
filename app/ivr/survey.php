<?php

namespace App\ivr;

use Illuminate\Http\Request;
use App\Models\Edd;
use App\Models\Call_back;
use App\Models\Call_behaviour;
use App\Models\Phc_notification;
use App\Models\Birth_contact;
use App\Models\Birth_notification;
use App\ivr\ivr_functions;
use App\ivr\call_thread;

class survey
{
    private $language;
    private $currentCallState;
    private $BirthCounter;
    private $HasBirthOccurred;
    private $NumberDelivered;
    private $eddID;
    private $parentID;
    private $Question;
    private $date;
    private object $ivrFunction;
    private object $call_thread;
    function __construct(String $language)
    {
        $this->ivrFunction=new ivr_functions();
        $this->call_thread=new call_thread();
        $this->language = $language;
    }
    function getEddId(){
        return $this->eddID;
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
        $this->currentCallState = $this->call_thread->repeatCallstate($request, $edd);
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
            $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?MothersName=Yes&ParentID=' . $value['parent_id'] . '" method="POST">';
            //$response .= '<Say voice="alice">Albarka! Thank you for contacting us. Please select the woman whose delivery you want to report.</Say>';
            $response .= "<Play>".URL('')."/audio/clips/$this->language/select-mother-prefix.mp3</Play>";
            //get the mothers' names and compose the message
            $count = 0;
            // if all the names for mothers in this family are fully recorded
            foreach ($edd as $key => $value) {
                //$response .= '<Say voice="alice">For</Say>';
                $response .= "<Play>".URL('')."/audio/clips/$this->language/for.mp3</Play>";
                $response .= "<Play>".URL('')."/audio/clips/names/" . $value['name']['filename'] . "</Play>";
                //$response .= '<Say voice="alice">Press</Say>';
                $response .= "<Play>".URL('')."/audio/clips/$this->language/press.mp3</Play>";
                $response .= $this->ivrFunction->callNumbersInWords(++$count,$this->language);
            }
            $response .= '</Gather>';
        } else {
            //tell the parent that a call would be routed to him later to manually get the content of the survey
            //$response .= '<Say voice="alice">Albarka! Thank you for contacting us. Our team would reach you in the shortest time to get your report through a call.</Say>';
            $response .= "<Play>".URL('')."/audio/clips/$this->language/call-back.mp3</Play>";
            $this->call_thread->scheduleACall($callerNumber);
            //send email notification of this call scheduled-call-for-birth-report
            $this->ivrFunction->sendEmailForCalls();
        }
        $response .= '</Response>';
        return $response; 
    }
    
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
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/report-in-full-message.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }/*
    function NoParentDetailMessage($language)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Say voice="alice">Sorry, parent detail not found! Thank you</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/no-parent-detail-message.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }
    function NoSurveyFoundMessage($language)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Say voice="alice">Sorry, no survey found! Thank you</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/no-survey-found.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }*/
    function IncorrectInputEndCall()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Say voice="alice">Oops! Input not correct. Please try again. Thank you</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/incorrect-input-end.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }
    function UnAuthorisedUserMessage()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Say voice="alice">Oops! We discovered you are not registered on the Avigo Health platform. Thank you</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/unauthorised-user-message.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }
    function ErrorMessage()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Say voice="alice">Oops! Just encountered an error. Please retry again</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/error-message.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }
    function EndCallAndAskaRecall()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        //$response .= "<Play>".URL('')."/audio/ivr/$this->language/error-message.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }
    function QuestionNone()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Gather finishOnKey="#">';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming" method="POST">';
        //$response .= '<Say voice="alice">Thank you for calling Albarka. Share your good news. Has the birth occurred? If Yes, Press 1. If No, Press 2. To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-none.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question-none?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function QuestionNoneErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Gather finishOnKey="#">';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=input" method="POST">';
        //$response .= '<Say voice="alice">The input you supplied for the last question is not correct. If birth has occurred, Press 1. Or Press 2 if birth has not occurred.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-none-error-input.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question-none?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question1()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming" method="POST">';
        //$response .= '<Say voice="alice">Enter the number of children born. To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-1.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question1?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question1ErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=input" method="POST">';
        //$response .= '<Say voice="alice">The input you supplied for the last question is not correct. Enter the number of children born. To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-1.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question1?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question1Negative()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Say voice="alice">Thank you for calling Albarka, feel free to reach us again when your wife gives birth.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-1-negative.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }
    function Question2A()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming" method="POST">';
        //$response .= '<Say voice="alice">What is the gender of the child? Press 1 for female or 2 for male. To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-2-A.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question2A?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question2AErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=input" method="POST">';
        //$response .= '<Say voice="alice">The input you supplied for the last question is not correct. If gender of the child is female Press 1 or press 2 for male. To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-2-A.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question2A?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question2B()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming" method="POST">';
        //$response .= '<Say voice="alice">How many of these children are boys? To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-2-B.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question2B?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question2BErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=input" method="POST">';
        //$response .= '<Say voice="alice">The input you supplied for the last question is not correct. How many of these children are boys? To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-2-B.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question2B?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question2C()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming" method="POST">';
        //$response .= '<Say voice="alice">How many of these children are girls? To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-2-C.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question-2-C?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question2CErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=input" method="POST">';
        //$response .= '<Say voice="alice">The input you supplied for the last question is not correct. How many of these children are girls? To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-2-C.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question-2-C?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question3()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming" method="POST">';
        //$response .= '<Say voice="alice">When was your child born? Press 0 for today, 1 for yesterday, 3 for three days ago, 7 for one week ago. To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-3.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question3?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question3Girl()
    {
        switch($this->language){
            case 'hausa':
                $response  = '<?xml version="1.0" encoding="UTF-8"?>';
                $response .= '<Response>';
                $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming" method="POST">';
                //$response .= '<Say voice="alice">When was your child born? Press 0 for today, 1 for yesterday, 3 for three days ago, 7 for one week ago. To listen to the question again press star.</Say>';
                $response .= "<Play>".URL('')."/audio/clips/$this->language/yaushe-ne-aka-haifi.mp3</Play>";
                $response .= "<Play>".URL('')."/audio/clips/$this->language/jaririan.mp3</Play>";
                $response .= "<Play>".URL('')."/audio/clips/$this->language/da-aka-samu-inda-yau-ne-dana-daya.mp3</Play>";
                $response .= '</Gather>';
                $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question3-girl?language=' . $this->language . '</Redirect>';
                $response .= '</Response>';
                break;
            
        }
        return $response;
    }
    function Question3ErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=input" method="POST">';
        //$response .= '<Say voice="alice">The input you supplied for the last question is not correct. Values can only be from 0 to 7. If your child was born today Press 0, yesterday press 1, three days ago press 4, one week ago press 7. To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-3.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question3?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question3MoreThanOneChild()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming" method="POST">';
        //$response .= '<Say voice="alice">When were your children born? Press 0 for today, 1 for yesterday, 3 for three days ago, 7 for one week ago. To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-3-more-than-one-child.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question3-more-than-one-child?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question3MoreThanOneChildErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=input" method="POST">';
        //$response .= '<Say voice="alice">The input you supplied for the last question is not correct. Values can only be from 0 to 7. If your children were born today Press 0, yesterday press 1, three days ago press 3, one week ago press 7. To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/hausa/error-prefix.mp3</Play>";
        $response .= "<Play>".URL('')."/audio/ivr/hausa/question-3-more-than-one-child.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question3-more-than-one-child?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question4()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming" method="POST">';
        //$response .= '<Say voice="alice"> When is the naming ceremony of the child? Press 0 for today, 1 for tomorrow, 3 for three days from now, 7 for one week from now. To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-4.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question4?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question4Girl()
    {
        switch($this->language){
            case 'hausa':
                $response  = '<?xml version="1.0" encoding="UTF-8"?>';
                $response .= '<Response>';
                $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming" method="POST">';
                //$response .= '<Say voice="alice">When was your child born? Press 0 for today, 1 for yesterday, 3 for three days ago, 7 for one week ago. To listen to the question again press star.</Say>';
                $response .= "<Play>".URL('')."/audio/clips/$this->language/yaushe-ne-ranar-sunar.mp3</Play>";
                $response .= "<Play>".URL('')."/audio/clips/$this->language/jaririan.mp3</Play>";
                $response .= "<Play>".URL('')."/audio/clips/$this->language/da-aka-samu-inda-yau-ne-dana-daya.mp3</Play>";
                $response .= '</Gather>';
                $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question4-girl?language=' . $this->language . '</Redirect>';
                $response .= '</Response>';
                break;
            
        }
        return $response;
    }
    function Question4ErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=input" method="POST">';
        //$response .= '<Say voice="alice"> The input you supplied for the last question is not correct. Values can only be from 0 to 7. If the naming ceremony is today Press 0, tomorrow press 1, three days from now press 3, one week from now press 7. To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-4.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question4?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question4MoreThanOneChild()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming" method="POST">';
        //$response .= '<Say voice="alice"> When is the naming ceremony of the children? Press 0 for today, 1 for tomorrow, 3 for three days from now, 7 for one week from now. To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-4-more-than-one-child.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question4-more-than-one-child?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question4MoreThanOneChildErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=input" method="POST">';
        //$response .= '<Say voice="alice"> The input you supplied for the last question is not correct. Values can only be from 0 to 7. If the naming ceremony is today Press 0, tomorrow press 1, three days from now press 3, one week from now press 7. To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-4-more-than-one-child.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question4-more-than-one-child?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question5()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming" method="POST">';
        // $response .= '<Say voice="alice">Do you want us to share news with your contacts? Press 1 for Yes or 0 for No. To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-5.mp3</Play>";
        $response .= '</Gather>';
        $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question5?language=' . $this->language . '</Redirect>';
        $response .= '</Response>';
        return $response;
    }
    function Question5ErrorInput($error)
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=input" method="POST">';
        //$response .= '<Say voice="alice">The input you supplied for the last question is not correct. If you want us to share your news with your contacts? Press 1 for Yes or 0 for No. To listen to the question again press star.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/error-prefix.mp3</Play>";
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-5.mp3</Play>";
        $response .= '</Gather>';
        if ($error != 'None') {
            //end call
            $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        } else {
            //repeat call
            $response .= '<Redirect method="POST">'.URL('').'/api/ivr-incoming/redirects/question5?language=' . $this->language . '</Redirect>';
        }
        $response .= '</Response>';
        return $response;
    }
    function Question6End()
    {
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Say voice="alice">Thanks for your time.</Say>';
        $response .= "<Play>".URL('')."/audio/ivr/$this->language/question-6-end.mp3</Play>";
        $response .= '</Response>';
        return $response;
    }

    
    
    /*
    
    

    
   
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
   
    repeatCallstate
        if (!is_bool($Presponse)) {
            mysqli_free_result($Presponse);
        }
    }*/
}
