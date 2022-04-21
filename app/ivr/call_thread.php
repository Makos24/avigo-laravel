<?php

namespace App\ivr;

use App\Models\Edd;
use App\Models\Call_behaviour;
use App\Models\Language;
use App\Models\Unauthorized_request;
use App\Models\Parent_detail;
use App\Models\Birth_notification;
use App\Models\Call_back;
use Illuminate\Http\Request;
use App\ivr\survey;
use App\ivr\notification;
use App\ivr\ivr_functions;

/**
 * handles ivr calls
 */
class call_thread
{
	private string $nameofuser;
	private string $callSessionState; //holds the state of the Twilio call, whether ringing or in progress
	private string $sessionId; //holds the session id of the call from Twilio
	private string $callerNumber; //phone number of the caller as provided by Twilio API
	private string $callerCarrierName; //the country of the caller
	private string $hangupCause; //holds the cause of call ending
	private string $direction; //the direction of the call incoming or out-going
	private int $duration; //holds the duration of call
	private string $date; //current timestamp
	private string $currentCallState; //the IVR thread to probe now
	private string $parentID; //the id of the parent making this call
	private string $eddID; //the id of the EDD associated with this call
	private string $numberOfMales; //the number of male children associated with this report
	private string $numberOfFemales; //the number of female children associated with this report
	private bool $mothersName; //determines if this caller is associated with a mother or more. false indicates one mother
	private string $error; //determines if the caller gave a wrong input for the second time or not
	private string $language; //holds the language of the parent
	private object $survey;
	private object $notification;
	private object $ivr_function;
	private $birthCounter;
	private $numberDelivered;
	private $phcPhone;
	private $phcEmail;
	private $phcID;
	private $wasLastInputCorrect;
	function __construct()
	{
		$this->ivr_function = new ivr_functions();
	}
	public function getEddId()
	{
		return $this->eddID;
	}
	public function getCaller()
	{
		return $this->callerNumber;
	}

	public function getParent()
	{
		$edd = new Edd();
		return $edd::with('parent', 'phc')->get();
	}
	public function getLanguage()
	{
		return $this->language;
	}
	function parentLanguage($parentID)
	{
		//get the language of this parent
		$parent = new Parent_detail();
		if ($parent::where('id', $parentID)->exists()) {
			return $parent::where('id', $parentID)->with('language')->first()->language->name;
		} else {
			return 'hausa';
		}
	}
	function parentIdFromPhone($phone)
	{
		//get the id of this parent from his number
		$parents = new Parent_detail();
		$parent = $parents::where('phone', $phone);
		if ($parent->exists()) {
			return $parent->first()->id;
		} else {
			return '';
		}
	}
	function parentLanguageFromPhone($phone)
	{
		//get the language of this parent from his number
		$parent = new Parent_detail();
		if ($parent::where('phone', $phone)->exists()) {
			return $parent::where('phone', $phone)->with('language')->first()->language->name;
		} else {
			return 'hausa';
		}
	}
	function parentLanguageFromContactEddId($eddid)
	{
		//get parent id of this edd id
		$edds = new Edd();
		$edd = $edds::where('id', $eddid)->first();
		//get the language of this parent from his number
		$parent = new Parent_detail();
		if ($parent::where('id', $edd->parent_id)->exists()) {
			return $parent::where('id', $edd->parent_id)->with('language')->first()->language->name;
		} else {
			return 'hausa';
		}
	}
	public function callerExists()
	{
		$callers = new Parent_detail();
		$caller = $callers::where('phone', $this->callerNumber);
		if ($caller->exists()) {
			$this->parentID = $caller->first()->id;
			return true;
		} else {
			return false;
		}
	}
	public function sessionIdExists()
	{
		$edds = new Edd();
		if ($edds::where('sessionid', $this->sessionId)->exists()) {
			return true;
		} else {
			return false;
		}
	}
	public function surveyExists()
	{
		$edds = new Edd();
		if ($edds::where('parent_id', $this->parentID)
			->where('survey_completed', '0')->exists()
		) {
			return true;
		} else {
			return false;
		}
	}
	public function unauthorizedCaller()
	{
		$edds = new Edd();
		if ($edds::where('parent_id', $this->parentID)
			->where('survey_completed', '0')->exists()
		) {
			return true;
		} else {
			return false;
		}
	}

	//gets entry point into survey
	public function instantiateSurvey(Request $request)
	{
		if (!$this->callerExists()) {
			//end call. This phone number is not registered 
			//if the phone number is not found in the database then record the number and quit
			$unauthorized_request = new Unauthorized_request();
			$unauthorized_request->sessionid = $this->sessionId;
			$unauthorized_request->phone = $this->callerNumber;
			$unauthorized_request->service_code = $this->callerCarrierName;
			$unauthorized_request->save();
			return $this->survey->UnAuthorisedUserMessage();
		}
		$edds = new Edd();
		$edd = $edds::with('name')->where('parent_id', $this->parentID)
			->where('survey_completed', 0)
			->orderByDesc('id')->get();
		$numberOfEDDs = $edd->count();
		//get the Edd info and current call state
		//check to see how many record of edd are left unreported
		if (!$numberOfEDDs > 0) {
			//end call
			$response = $this->survey->NoSessionRemainingMessage();
		}
		if ($numberOfEDDs == 1) {
			//if the returned number of EDDs for this family is one
			$response = $this->survey->mainEntryToSurvey($request, $edd->first(), $this->sessionId);
			$this->eddID = $this->survey->getEddId();
		} else {
			//if the number of EDDs for this family is more than one, get the mother's name and use that to differentiate
			//form the message
			$response = $this->survey->composeQuestionForMothersNameSelection($request, $edd, $this->callerNumber);
		}
		//enter call behaviour
		$this->setCallBehaviour($this->sessionId, $this->direction, $this->callSessionState, $this->date, $this->callerNumber, $this->eddID, $this->parentID, 'inbound-voice-call-callback-uri/index.php');
		return $response;
	}
	//handles the IVR session checks if the session exists ,gets the next question or gets an entry point into the survey
	public function handleIvrSession(Request $request)
	{
		if ($this->mothersName == true) {
			//this is coming from the mothers' name question
			//get the Edd info and current call state
			$edds = new Edd();
			$edd = $edds::where('parent_id', $this->parentID)
				->where('survey_completed', 0)
				->orderByDesc('id');
			if (!$edd->exists()) {
				//if there are no uncompleted surveys
				return $this->survey->NoSessionRemainingMessage();
			}
			$edd = $edd->get();
			if ($request->input('Digits') < 1) {
				//the input was not correct, input must be greater than 1
				return $this->survey->IncorrectInputEndCall();
			}
			if ($request->input('Digits') > $edd->count()) {
				//the input was not correct, input cannot be greater than the number of edds attached to the phone number 
				return $this->survey->IncorrectInputEndCall();
			}
			//the input was correct
			//get the correct eddid of selected mother and instantiate survey
			if ($request->input('Digits') == 1) {
				return $this->survey->mainEntryToSurvey($request, $edd->take($request->input('Digits'))->first(), $this->sessionId);
			} else {
				//this function $request->input('Digits')-1)->take(1)->first() tracks the edd id of the selected mother
				return $this->survey->mainEntryToSurvey($request, $edd->skip($request->input('Digits') - 1)->take(1)->first(), $this->sessionId);
			}
		}
		if (!$this->sessionIdExists()) {
			//if this session id does not exist try to instantiate survey, this would help to validate the caller and check for existing surveys if available
			return $this->instantiateSurvey($request);
		}
		//if the session id exists then get the next survey question and proceed
		$edds = new Edd();
		$edd = $edds::with('phc')->where('sessionid', $this->sessionId)->first();
		$this->numberOfMales = $edd->male;
		$this->numberOfFemales = $edd->female;
		$this->parentID = $edd->parent_id;
		$this->eddID = $edd->id;
		$this->wasLastInputCorrect = $edd->was_last_input_correct;
		$this->currentCallState = $edd->current_call_state;
		$this->previousCallState = $edd->previous_call_state;
		$this->birthCounter = $edd->birth_counter;
		$this->numberDelivered = $edd->number_delivered;
		$this->phcPhone = $edd->phone;
		$this->phcEmail = $edd->email;
		$this->phcID = $edd->phc_id;
		$this->language = $this->parentLanguage($this->parentID);
		//check to know if the caller wants a repeat of a question
		$this->currentCallState = $this->repeatCallstate($request, $edd);
		//enter call behaviour
		$this->setCallBehaviour($this->sessionId, $this->direction, $this->callSessionState, $this->date, $this->callerNumber, $this->eddID, $this->parentID, 'inbound-voice-call-callback-uri/index.php');
		return $this->retrieveSurveyQuestions($request, $edd, $this->currentCallState);
	}
	public function retrieveSurveyQuestions(Request $request, Object $edd, $currentCallState)
	{
		switch ($currentCallState) {
			case '':
			case 'None':
				$edd->previous_call_state = 'None';
				$edd->current_call_state = 'Question1';
				$edd->start_time = $this->date;
				$edd->end_time = $this->date;
				$edd->was_last_input_correct = 1;
				if ($edd->save()) {
					//introduce avigo health and ask if birth has occurred
					$response = $this->survey->QuestionNone();
				} else {
					// Compose the response
					$response = $this->survey->ErrorMessage();
				}
				break;
			case 'Question1':
				$response=$this->question1($request, $edd);
				/* $dtmfDigits = $request->input('Digits');
				if ($dtmfDigits == '*') {
					//a repeat of previous question is needed, make the previous state the current state
					$response = $this->survey->QuestionNone();
				} else {
					//check for erroneous input
					if ($dtmfDigits == 1 or $dtmfDigits == 2) {
						//get whether there was a birth        
						//update previous response to questionNone which is whether birth has occurred?
						if ($dtmfDigits == 1) {
							//next question to ask is gender of children or how many children are male (Question2)
							$edd->previous_call_state = 'Question1';
							$edd->current_call_state = 'Question2';
							$edd->has_birth_occurred = $dtmfDigits;
							$edd->end_time = $this->date;
							$edd->was_last_input_correct = 1;
						} else {
							$dtmfDigits = 0;
							//roll back to the last question 
							//next question to ask how many children were born (Question1)
							$edd->previous_call_state = 'None';
							$edd->current_call_state = 'Question1';
							$edd->has_birth_occurred = $dtmfDigits;
							$edd->end_time = $this->date;
							$edd->was_last_input_correct = 1;
						}
						if ($edd->save()) {
							if ($dtmfDigits == 1) {
								// Compose the response
								//ask of the number of children born?
								$response = $this->survey->Question1();
							} else {
								// Compose the response
								//end the conversation, since birth has not occurred yet
								$response = $this->survey->Question1Negative();
							}
						} else {
							// Compose the response
							$response = $this->survey->ErrorMessage();
						}
					} else { //ensure call state is correct
						//input is erroneous
						//check if this is the second time this input is not correct
						if ($this->error == 'silence' or $this->error == 'input') {
							// end call and ask the user to redial
							$response = $this->survey->EndCallAndAskaRecall();
						} else {
							//proceed to repeat call
							$this->error = 'input';
							$response = $this->survey->QuestionNoneErrorInput($this->error);
						}
					}
				} */
				break;
			case 'Question2':
				$response=$this->question2($request, $edd);
				/* if ($request->input('Digits') == '*') {
					//a repeat of previous question is needed, make the previous state the current state
					$response = $this->survey->Question1();
				} else {
					//check if the input is correct or not(that is the number of children is neither alphabet nor 0)
					if (is_numeric($request->input('Digits')) == true and $request->input('Digits') > 0) {
						//the input is a number and is not 0   
						$this->numberDelivered = $request->input('Digits');
						$Question = '';
						if ($this->numberDelivered == '1') {
							//next question to ask is when was (were) the child(ren) born?
							$Question = 'Question3';
						} else {
							//next question to ask is How many children are girls? 
							$Question = 'Question2C';
						}
						//update the previous question which is number of children born if the returned digit is not a *
						$edd->previous_call_state = 'Question2';
						$edd->current_call_state = $Question;
						$edd->end_time = $this->date;
						$edd->number_delivered = $this->numberDelivered;
						$edd->was_last_input_correct = 1;
						if ($edd->save()) {
							if ($this->numberDelivered == '1') { //if only one child was born
								// Compose the response
								//ask for the gender of the child, 1 for female and 2 for male
								$response = $this->survey->Question2A();
							} else { //if more than one child was born
								// Compose the response
								//ask how many are boys
								$response = $this->survey->Question2B();
							}
						} else {
							// Compose the response
							$response = $this->survey->ErrorMessage();
						}
					} else {
						//inform user of the wrong question and ask for a correct input
						//check if this is the second time this input is not correct
						if ($this->error == 'silence' or $this->error == 'input') {
							// end call and ask the user to redial
							$response = $this->survey->EndCallAndAskaRecall();
						} else {
							//proceed to repeat call
							$this->error = 'input';
							$response = $this->survey->Question1ErrorInput($this->error);
						}
					}
				} */
				break;
			case 'Question2C':
				$response=$this->question2c($request, $edd);
				/* if ($request->input('Digits') == '*') {
					//repeat previously asked question
					if ($this->numberDelivered == '1') { //if only one child was born
						// Compose the response
						//ask for the gender of the child, 1 for female and 2 for male
						$response = $this->survey->Question2A();
					} else { //if more than one child was born
						// Compose the response
						//ask how many are boys
						$response = $this->survey->Question2B();
					}
				} else {
					//update response to previous question
					//update the edd sessionID field and current call state to second question        
					//get the number of female children born
					$dtmfDigits = $request->input('Digits');
					$Question = 'Question3';
					if (is_numeric($dtmfDigits) == true and $dtmfDigits > 0 and $dtmfDigits < 10 and $dtmfDigits < $this->numberDelivered) {
						$edd->previous_call_state = 'Question2C';
						$edd->current_call_state = $Question;
						$edd->end_time = $this->date;
						$edd->male = $dtmfDigits;
						$edd->was_last_input_correct = 1;
						if ($edd->save()) {
							//ask of the number of female children
							$response = $this->survey->Question2C();
						} else {
							// Compose the response
							$response = $this->survey->ErrorMessage();
						}
					} elseif (is_numeric($dtmfDigits) == true and $dtmfDigits > 0 and $dtmfDigits < 10 and $dtmfDigits == $this->numberDelivered) {
						$Question = 'Question4';
						$edd->previous_call_state = 'Question3';
						$edd->current_call_state = $Question;
						$edd->end_time = $this->date;
						$edd->male = $dtmfDigits;
						$edd->female = 0;
						$edd->was_last_input_correct = 1;
						if ($edd->save()) {
							//ask of the day of delivery
							//$response = $this->survey->Question3();
							if($this->numberDelivered==1){
								$response = $this->survey->Question3();
							}else{
								$response = $this->survey->Question3MoreThanOneChild();
							}
						} else {
							// Compose the response
							$response = $this->survey->ErrorMessage();
						}
					} else {
						//the last input to question 2B was out of range, repeat question again
						//check if this is the second time this input is not correct
						if ($this->error == 'silence' or $this->error == 'input') {
							// end call and ask the user to redial
							$response = $this->survey->EndCallAndAskaRecall();
						} else {
							//proceed to repeat call
							$this->error = 'input';
							$response = $this->survey->Question2BErrorInput($this->error);
						}
					}
				} */
				break;
			case 'Question3':
				$response=$this->question3($request, $edd);
				/* if ($request->input('Digits') == '*') {
					//repeat previous question
					if ($this->numberDelivered == 1) {
						//ask what is the gender of the child
						$response = $this->survey->Question2A();
					} else {
						//ask how many children are female
						$response = $this->survey->Question2C();
					}
				} else {
					$dtmfDigits = $_POST['Digits'];
					//check the previous question to now how to treat http input
					switch ($this->previousCallState) {
						case 'Question2C':
							if (is_numeric($dtmfDigits) and $dtmfDigits > 0 and $dtmfDigits < 10 and ($dtmfDigits + $this->numberOfMales) == $this->numberDelivered) {
								$edd->previous_call_state = 'Question3';
								$edd->current_call_state = 'Question4';
								$edd->end_time = $this->date;
								$edd->female = $dtmfDigits;
								$edd->was_last_input_correct = 1;
								if ($edd->save()) {
									// Compose the response
									$response = $this->survey->Question3MoreThanOneChild();
								} else {
									// Compose the response
									$response = $this->survey->ErrorMessage();
								}
							} else {
								//the last input to question 2B was out of range, repeat question again
								//check if this is the second time this input is not correct
								if ($this->error == 'silence' or $this->error == 'input') {
									// end call and ask the user to redial
									$response = $this->survey->EndCallAndAskaRecall();
								} else {
									//proceed to repeat call
									$this->error = 'input';
									$response = $this->survey->Question2CErrorInput($this->error);
								}
							}
							break;
						case 'Question2':
							//this came from question 2 (that is a 1 for female or 2 for male)
							if ($dtmfDigits == 1 or $dtmfDigits == 2) {
								//check the gender of the child
								$Female = 0;
								$Male = 0;
								if ($request->input('Digits') == 1) {
									//the child is female
									$Female = 1;
								} elseif ($request->input('Digits') == 2) {
									//the child is male
									$Male = 1;
								}
								$edd->previous_call_state = 'Question3';
								$edd->current_call_state = 'Question4';
								$edd->end_time = $this->date;
								$edd->female = $Female;
								$edd->male = $Male;
								$edd->was_last_input_correct = 1;
								if ($edd->save()) {
									// Compose the response
									if($Female==0){
										$response = $this->survey->Question3();
									}else{
										$response = $this->survey->Question3Girl();
									}
								} else {
									// Compose the response
									$response = $this->survey->ErrorMessage();
								}
							} else {
								//re-ask question 2A due to errorneous input
								//check if this is the second time this input is not correct
								if ($this->error == 'silence' or $this->error == 'input') {
									// end call and ask the user to redial
									$response = $this->survey->EndCallAndAskaRecall();
								} else {
									//proceed to repeat call
									$this->error = 'input';
									$response = $this->survey->Question2AErrorInput($this->error);
								}
							}
							break;
					}
				} */
				break;
			case 'Question4';
				$response=$this->question4($request, $edd);
				/* //update response to previous question
				//update the edd sessionID field and current call state to second question        
				//get whether there was a birth
				$dtmfDigits = $request->input('Digits');
				$dod = $this->ivr_function->getDatePast($dtmfDigits);
				if ($request->input('Digits') == '*') {
					if ($this->numberDelivered == 1) {
						//$response = $this->survey->Question3();
						if($edd->female==0){
							$response = $this->survey->Question3();
						}else{
							$response = $this->survey->Question3Girl();
						}
					} else {
						$response = $this->survey->Question3MoreThanOneChild();
					}
				} else {
					if ($dtmfDigits >= 0 and $dtmfDigits <= 7) {
						$edd->previous_call_state = 'Question4';
						$edd->current_call_state = 'Question5';
						$edd->end_time = $this->date;
						$edd->dod = $dod;
						$edd->was_last_input_correct = 1;
						if ($edd->save()) {
							//insert gender of child
							if ($this->numberDelivered == 1) {
								//$response = $this->survey->Question4();
								if($edd->female==0){
									$response = $this->survey->Question4();
								}else{
									$response = $this->survey->Question4Girl();
								}
							} else {
								$response = $this->survey->Question4MoreThanOneChild();
							}
						} else {
							// Compose the response
							$response = $this->survey->ErrorMessage();
						}
					} else {
						if ($this->numberDelivered == 1) {
							//check if this is the second time this input is not correct
							if ($this->error == 'silence' or $this->error == 'input') {
								// end call and ask the user to redial
								$response = $this->survey->EndCallAndAskaRecall();
							} else {
								//proceed to repeat call
								$response = $this->survey->Question3ErrorInput($this->error);
							}
						} else {
							//check if this is the second time this input is not correct
							if ($this->error == 'silence' or $this->error == 'input') {
								// end call and ask the user to redial
								$response = $this->survey->EndCallAndAskaRecall();
							} else {
								//proceed to repeat call
								$this->error = 'input';
								$response = $this->survey->Question3MoreThanOneChildErrorInput($this->error);
							}
						}
					}
				} */
				break;
			case 'Question5':
				$response=$this->question5($request, $edd);
				/* //update response to previous question
				//update the edd sessionID field and current call state to second question        
				//get whether there was a birth
				$dtmfDigits = $request->input('Digits');
				$doc = $this->ivr_function->getDateAhead($dtmfDigits);
				if ($request->input('Digits') == '*') {
					//repeat previous question that is question 4
					if ($this->numberDelivered == 1) {
						//$response = $this->survey->Question4();
						if($edd->female==0){
							$response = $this->survey->Question4();
						}else{
							$response = $this->survey->Question4Girl();
						}
					} else {
						$response = $this->survey->Question4MoreThanOneChild();
					}
				} else {
					//update health facilities notifications
					$this->ivr_function->updatePhcNotification($this->phcID, $this->phcPhone, $this->phcEmail, $this->numberDelivered);
					//update call status
					if ($dtmfDigits >= 0 and $dtmfDigits <= 7) {
						$edd->previous_call_state = 'Question5';
						$edd->current_call_state = 'QuestionEnd';
						$edd->end_time = $this->date;
						$edd->doc = $doc;
						$edd->was_last_input_correct = 1;
						if ($edd->save()) {
							//insert gender of child
							$response = $this->survey->Question5();
						} else {
							// Compose the response
							$response = $this->survey->ErrorMessage();
						}
					} else {
						if ($this->numberDelivered == 1) {
							//check if this is the second time this input is not correct
							if ($this->error == 'silence' or $this->error == 'input') {
								// end call and ask the user to redial
								$response = $this->survey->EndCallAndAskaRecall();
							} else {
								//proceed to repeat call
								$this->error = 'input';
								$response = $this->survey->Question4ErrorInput($this->error);
							}
						} else {
							//check if this is the second time this input is not correct
							if ($this->error == 'silence' or $this->error == 'input') {
								// end call and ask the user to redial
								$response = $this->survey->EndCallAndAskaRecall();
							} else {
								//proceed to repeat call
								$this->error = 'input';
								$response = $this->survey->Question4MoreThanOneChildErrorInput($this->error);
							}
						}
					}
				} */
				break;
			case 'QuestionEnd':
				$response=$this->questionend($request, $edd);
				/* //update response to previous question
				//update the edd sessionID field and current call state to second question        
				$dtmfDigits = $request->input('Digits');
				if ($request->input('Digits') == '*') {
					//repeat question 5
					$response = $this->survey->Question5();
				} else {
					if ($dtmfDigits == 2 or $dtmfDigits == 1) {
						$edd->previous_call_state = 'QuestionEnd';
						$edd->current_call_state = 'SessionEnd';
						$edd->end_time = $this->date;
						$edd->notif_auth = $dtmfDigits;
						$edd->survey_completed = 1;
						$edd->was_last_input_correct = 1;
						if ($edd->save()) {
							//set contact notification if allowed
							if ($dtmfDigits == 1) {
								//add contacts to notification table
								$this->ivr_function->setUpContactNotification($this->parentID, $this->eddID);
								//call notification script here
								$this->notification->notifyContactWithThisEddID($this->eddID);
							}
							//call notification script here
							$response = $this->survey->Question6End();
						} else {
							// Compose the response
							$response = $this->survey->ErrorMessage();
						}
					} else {
						//check if this is the second time this input is not correct
						if ($this->error == 'silence' or $this->error == 'input') {
							// end call and ask the user to redial
							$response = $this->survey->EndCallAndAskaRecall();
						} else {
							//proceed to repeat call
							$this->error = 'input';
							$response = $this->survey->Question5ErrorInput($this->error);
						}
					}
				} */
				break;
			default:
				$response = $this->survey->NoSessionRemainingMessage();
				break;
		}
		return $response;
	}
	function question1(Request $request, Object $edd){
		$dtmfDigits = $request->input('Digits');
		if ($dtmfDigits == '*') {
			//a repeat of previous question is needed, make the previous state the current state
			return $this->survey->QuestionNone();
		} 
		//check for erroneous input
		if ($dtmfDigits == 1 or $dtmfDigits == 2) {
			//get whether there was a birth        
			//update previous response to questionNone which is whether birth has occurred?
			if ($dtmfDigits == 1) {
				//next question to ask is gender of children or how many children are male (Question2)
				$edd->previous_call_state = 'Question1';
				$edd->current_call_state = 'Question2';
				$edd->has_birth_occurred = $dtmfDigits;
				$edd->end_time = $this->date;
				$edd->was_last_input_correct = 1;
			} else {
				$dtmfDigits = 0;
				//roll back to the last question 
				//next question to ask how many children were born (Question1)
				$edd->previous_call_state = 'None';
				$edd->current_call_state = 'Question1';
				$edd->has_birth_occurred = $dtmfDigits;
				$edd->end_time = $this->date;
				$edd->was_last_input_correct = 1;
			}
			if ($edd->save()) {
				if ($dtmfDigits == 1) {
					// Compose the response
					//ask of the number of children born?
					return $this->survey->Question1();
				} 
				// Compose the response
				//end the conversation, since birth has not occurred yet
				return $this->survey->Question1Negative();
			} 
			// Compose the response
			return $this->survey->ErrorMessage();
		}
		//input is erroneous
		//check if this is the second time this input is not correct
		if ($this->error == 'silence' or $this->error == 'input') {
			// end call and ask the user to redial
			return $this->survey->EndCallAndAskaRecall();
		} 
		//proceed to repeat call
		$this->error = 'input';
		return $this->survey->QuestionNoneErrorInput($this->error);
	}
	function question2(Request $request,Object $edd){
		if ($request->input('Digits') == '*') {
			//a repeat of previous question is needed, make the previous state the current state
			return $this->survey->Question1();
		}
		//check if the input is correct or not(that is the number of children is neither alphabet nor 0)
		if (is_numeric($request->input('Digits')) == true and $request->input('Digits') > 0) {
			//the input is a number and is not 0   
			$this->numberDelivered = $request->input('Digits');
			$Question = '';
			if ($this->numberDelivered == '1') {
				//next question to ask is when was (were) the child(ren) born?
				$Question = 'Question3';
			} else {
				//next question to ask is How many children are girls? 
				$Question = 'Question2C';
			}
			//update the previous question which is number of children born if the returned digit is not a *
			$edd->previous_call_state = 'Question2';
			$edd->current_call_state = $Question;
			$edd->end_time = $this->date;
			$edd->number_delivered = $this->numberDelivered;
			$edd->was_last_input_correct = 1;
			if ($edd->save()) {
				if ($this->numberDelivered == '1') { //if only one child was born
					// Compose the response
					//ask for the gender of the child, 1 for female and 2 for male
					return $this->survey->Question2A();
				} 
				//if more than one child was born
				// Compose the response
				//ask how many are boys
				return $this->survey->Question2B();
			} 
			// Compose the response
			return $this->survey->ErrorMessage();
		} 
		//inform user of the wrong question and ask for a correct input
		//check if this is the second time this input is not correct
		if ($this->error == 'silence' or $this->error == 'input') {
			// end call and ask the user to redial
			return $this->survey->EndCallAndAskaRecall();
		} 
		//proceed to repeat call
		$this->error = 'input';
		return $this->survey->Question1ErrorInput($this->error);	
	}
	function question2c(Request $request, Object $edd){
		if ($request->input('Digits') == '*') {
			//repeat previously asked question
			if ($this->numberDelivered == '1') { //if only one child was born
				// Compose the response
				//ask for the gender of the child, 1 for female and 2 for male
				return $this->survey->Question2A();
			} 
			//if more than one child was born
			// Compose the response
			//ask how many are boys
			return $this->survey->Question2B();
		} 
		//update response to previous question
		//update the edd sessionID field and current call state to second question        
		//get the number of female children born
		$dtmfDigits = $request->input('Digits');
		$Question = 'Question3';
		if (is_numeric($dtmfDigits) == true and $dtmfDigits > 0 and $dtmfDigits < 10 and $dtmfDigits < $this->numberDelivered) {
			$edd->previous_call_state = 'Question2C';
			$edd->current_call_state = $Question;
			$edd->end_time = $this->date;
			$edd->male = $dtmfDigits;
			$edd->was_last_input_correct = 1;
			if ($edd->save()) {
				//ask of the number of female children
				return $this->survey->Question2C();
			} 
			// Compose the response
			return $this->survey->ErrorMessage();
		} elseif (is_numeric($dtmfDigits) == true and $dtmfDigits > 0 and $dtmfDigits < 10 and $dtmfDigits == $this->numberDelivered) {
			$Question = 'Question4';
			$edd->previous_call_state = 'Question3';
			$edd->current_call_state = $Question;
			$edd->end_time = $this->date;
			$edd->male = $dtmfDigits;
			$edd->female = 0;
			$edd->was_last_input_correct = 1;
			if ($edd->save()) {
				//ask of the day of delivery
				//return $this->survey->Question3();
				if($this->numberDelivered==1){
					return $this->survey->Question3();
				}
				return $this->survey->Question3MoreThanOneChild();
			} 
			// Compose the response
			return $this->survey->ErrorMessage();
		} else {
			//the last input to question 2B was out of range, repeat question again
			//check if this is the second time this input is not correct
			if ($this->error == 'silence' or $this->error == 'input') {
				// end call and ask the user to redial
				return $this->survey->EndCallAndAskaRecall();
			}
			//proceed to repeat call
			$this->error = 'input';
			return $this->survey->Question2BErrorInput($this->error);
		}
		
	}
	function question3(Request $request, Object $edd){
		if ($request->input('Digits') == '*') {
			//repeat previous question
			if ($this->numberDelivered == 1) {
				//ask what is the gender of the child
				return $this->survey->Question2A();
			} 
			//ask how many children are female
			return $this->survey->Question2C();
		} 
		$dtmfDigits = $_POST['Digits'];
		//check the previous question to now how to treat http input
		switch ($this->previousCallState) {
			case 'Question2C':
				if (is_numeric($dtmfDigits) and $dtmfDigits > 0 and $dtmfDigits < 10 and ($dtmfDigits + $this->numberOfMales) == $this->numberDelivered) {
					$edd->previous_call_state = 'Question3';
					$edd->current_call_state = 'Question4';
					$edd->end_time = $this->date;
					$edd->female = $dtmfDigits;
					$edd->was_last_input_correct = 1;
					if ($edd->save()) {
						// Compose the response
						$response = $this->survey->Question3MoreThanOneChild();
						break;
					} 
					// Compose the response
					$response = $this->survey->ErrorMessage();
					break;
				} 
				//the last input to question 2B was out of range, repeat question again
				//check if this is the second time this input is not correct
				if ($this->error == 'silence' or $this->error == 'input') {
					// end call and ask the user to redial
					$response = $this->survey->EndCallAndAskaRecall();
					break;
				} 
				//proceed to repeat call
				$this->error = 'input';
				$response = $this->survey->Question2CErrorInput($this->error);
				break;
			case 'Question2':
				//this came from question 2 (that is a 1 for female or 2 for male)
				if ($dtmfDigits == 1 or $dtmfDigits == 2) {
					//check the gender of the child
					$Female = 0;
					$Male = 0;
					if ($request->input('Digits') == 1) {
						//the child is female
						$Female = 1;
					} elseif ($request->input('Digits') == 2) {
						//the child is male
						$Male = 1;
					}
					$edd->previous_call_state = 'Question3';
					$edd->current_call_state = 'Question4';
					$edd->end_time = $this->date;
					$edd->female = $Female;
					$edd->male = $Male;
					$edd->was_last_input_correct = 1;
					if ($edd->save()) {
						// Compose the response
						if($Female==0){
							$response = $this->survey->Question3();
							break;
						}
						$response = $this->survey->Question3Girl();
						break;
					} 
					// Compose the response
					$response = $this->survey->ErrorMessage();
					break;
					
				} 
				//re-ask question 2A due to errorneous input
				//check if this is the second time this input is not correct
				if ($this->error == 'silence' or $this->error == 'input') {
					// end call and ask the user to redial
					$response = $this->survey->EndCallAndAskaRecall();
					break;
				} 
				//proceed to repeat call
				$this->error = 'input';
				$response = $this->survey->Question2AErrorInput($this->error);
				break;
		}
		return $response;
	}
	function question4(Request $request, Object $edd){
		//update response to previous question
		//update the edd sessionID field and current call state to second question        
		//get whether there was a birth
		$dtmfDigits = $request->input('Digits');
		$dod = $this->ivr_function->getDatePast($dtmfDigits);
		if ($request->input('Digits') == '*') {
			if ($this->numberDelivered == 1) {
				//$response = $this->survey->Question3();
				if($edd->female==0){
					return $this->survey->Question3();
				}
				return $this->survey->Question3Girl();
			} 
			return $this->survey->Question3MoreThanOneChild();
		} 
		if ($dtmfDigits >= 0 and $dtmfDigits <= 7) {
			$edd->previous_call_state = 'Question4';
			$edd->current_call_state = 'Question5';
			$edd->end_time = $this->date;
			$edd->dod = $dod;
			$edd->was_last_input_correct = 1;
			if ($edd->save()) {
				//insert gender of child
				if ($this->numberDelivered == 1) {
					//$response = $this->survey->Question4();
					if($edd->female==0){
						return $this->survey->Question4();
					}else{
						return $this->survey->Question4Girl();
					}
				} else {
					return $this->survey->Question4MoreThanOneChild();
				}
			} else {
				// Compose the response
				return $this->survey->ErrorMessage();
			}
		} 
		if ($this->numberDelivered == 1) {
			//check if this is the second time this input is not correct
			if ($this->error == 'silence' or $this->error == 'input') {
				// end call and ask the user to redial
				return $this->survey->EndCallAndAskaRecall();
			} 
			//proceed to repeat call
			return $this->survey->Question3ErrorInput($this->error);
		}
		//check if this is the second time this input is not correct
		if ($this->error == 'silence' or $this->error == 'input') {
			// end call and ask the user to redial
			return $this->survey->EndCallAndAskaRecall();
		} 
		//proceed to repeat call
		$this->error = 'input';
		return $this->survey->Question3MoreThanOneChildErrorInput($this->error);		
	}
	function question5(Request $request, Object $edd){
		//update response to previous question
		//update the edd sessionID field and current call state to second question        
		//get whether there was a birth
		$dtmfDigits = $request->input('Digits');
		$doc = $this->ivr_function->getDateAhead($dtmfDigits);
		if ($request->input('Digits') == '*') {
			//repeat previous question that is question 4
			if ($this->numberDelivered == 1) {
				//$response = $this->survey->Question4();
				if($edd->female==0){
					return $this->survey->Question4();
				}
				return $this->survey->Question4Girl();
			}
			return $this->survey->Question4MoreThanOneChild();
		} 
		//update health facilities notifications
		$this->ivr_function->updatePhcNotification($this->phcID, $this->phcPhone, $this->phcEmail, $this->numberDelivered);
		//update call status
		if ($dtmfDigits >= 0 and $dtmfDigits <= 7) {
			$edd->previous_call_state = 'Question5';
			$edd->current_call_state = 'QuestionEnd';
			$edd->end_time = $this->date;
			$edd->doc = $doc;
			$edd->was_last_input_correct = 1;
			if ($edd->save()) {
				//insert gender of child
				return $this->survey->Question5();
			}
			// Compose the response
			return $this->survey->ErrorMessage();
		} 
		if ($this->numberDelivered == 1) {
			//check if this is the second time this input is not correct
			if ($this->error == 'silence' or $this->error == 'input') {
				// end call and ask the user to redial
				return $this->survey->EndCallAndAskaRecall();
			} 
			//proceed to repeat call
			$this->error = 'input';
			return $this->survey->Question4ErrorInput($this->error);
		} 
		//check if this is the second time this input is not correct
		if ($this->error == 'silence' or $this->error == 'input') {
			// end call and ask the user to redial
			return $this->survey->EndCallAndAskaRecall();
		}
		//proceed to repeat call
		$this->error = 'input';
		return $this->survey->Question4MoreThanOneChildErrorInput($this->error);		
	}
	function questionend(Request $request, Object $edd){
		//update response to previous question
		//update the edd sessionID field and current call state to second question        
		$dtmfDigits = $request->input('Digits');
		if ($request->input('Digits') == '*') {
			//repeat question 5
			return $this->survey->Question5();
		} 
		if ($dtmfDigits == 2 or $dtmfDigits == 1) {
			$edd->previous_call_state = 'QuestionEnd';
			$edd->current_call_state = 'SessionEnd';
			$edd->end_time = $this->date;
			$edd->notif_auth = $dtmfDigits;
			$edd->survey_completed = 1;
			$edd->was_last_input_correct = 1;
			if ($edd->save()) {
				//set contact notification if allowed
				if ($dtmfDigits == 1) {
					//add contacts to notification table
					$this->ivr_function->setUpContactNotification($this->parentID, $this->eddID);
					//call notification script here
					$this->notification->notifyContactWithThisEddID($this->eddID);
				}
				//call notification script here
				return $this->survey->Question6End();
			} 
			// Compose the response
			return $this->survey->ErrorMessage();
		} 
		//check if this is the second time this input is not correct
		if ($this->error == 'silence' or $this->error == 'input') {
			// end call and ask the user to redial
			return $this->survey->EndCallAndAskaRecall();
		} 
		//proceed to repeat call
		$this->error = 'input';
		return $this->survey->Question5ErrorInput($this->error);
	}
	/*this ensures the phone number is in the international format as required */
	public function ensureNumberIsFormattedCorrectly(string $phoneNumber)
	{
		$findme = '234';
		$pos = stripos($phoneNumber, $findme); //gets the position of the characters 234 in the phone number string
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
	/*saves the call impression*/
	public function saveCallBehaviour(string $uri = "")
	{
		$calls = new Call_behaviour();
		$call = $calls::where('sessionid', $this->sessionId);
		if ($call->exists()) {
			$call = $call->first();
			$call->duration = 0;
			$call->cost = 0;
			$call->status = $this->callSessionState;
			$call->hang_up_cause = $this->hangupCause;
			$call->end_date = $this->date;
			$call->final_call_status = $this->callSessionState;
			$call->save();
		} else {
			$calls->sessionid = $this->sessionId;
			$call->duration = 0;
			$call->cost = 0;
			$call->status = $this->callSessionState;
			$calls->phone = $this->callerNumber;
			$calls->start_date = $this->date;
			$calls->end_date = $this->date;
			$calls->uri = $uri;
			$calls->call_direction = $this->direction;
			$calls->final_call_status = $this->callSessionState;
			$calls->save();
		}
	}
	function setCallBehaviour($sessionId, $direction, $callSessionState, $date, $callerNumber, $EddID, $ParentID, $uri = '')
	{
		$calls = new Call_behaviour();
		$call = $calls::where('sessionid', $sessionId)
			->where('phone', $callerNumber)
			->where('edd_id', $EddID)
			->where('parent_id', $ParentID);
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
	/*this method initializes the call thread with the call parameters as obtained from Twilio, first parameter $POST is the $_POST variable and second parameter $GET is the $_GET variable from the Twilio call*/
	public function initialize_call(Request $request)
	{
		$this->callSessionState = $request->input('CallStatus');
		$this->hangupCause = $request->input('CallStatus');
		$this->sessionId = $request->input('CallSid');
		$this->direction = $request->input('Direction');
		$this->parentID = '';
		if ($request->has('CallDuration')) {
			//coming from a call back
			$this->duration = $request->input('CallDuration');
		}
		if ($request->has('MothersName')) {
			//coming from the question requesting to choose a mother's name
			$this->mothersName = true;
			$this->parentID = $request->query('ParentID');
		} else {
			//coming from the normal survey questions
			$this->mothersName = false;
		}
		if ($request->has('EddID')) {
			$this->eddID = $request->query('EddID');
		} else {
			$this->eddID = '';
		}
		if ($request->has('Caller')) {
			//this is from an incoming call
			$this->callerNumber = $this->ensureNumberIsFormattedCorrectly($request->input('Caller'));
			$this->parentID = $this->parentIdFromPhone($this->callerNumber);
			$this->language = $this->parentLanguage($this->parentID);
		} else {
			//this is from an outgoing call
			$this->callerNumber = $this->ensureNumberIsFormattedCorrectly($request->input('Called'));
			switch ($request->query('Type')) {
				case 'EDD_Reg':
					$this->language = $this->parentLanguageFromPhone($this->callerNumber);
					break;
				case 'Birth_Report_Reminder':
					$this->language = $this->parentLanguageFromPhone($this->callerNumber);
					break;
				case 'Contact_Notif':
					$this->language = $this->parentLanguageFromContactEddId($this->eddID);
					break;
			}
		}
		if ($request->has('CallerCountry')) {
			$this->callerCarrierName = $request->input('CallerCountry');
		} else {
			$this->callerCarrierName = '';
		}

		$this->duration = 0;
		$this->date = date('Y-m-d h:i:s');

		$this->numberOfMales = 0;
		$this->numberOfFemales = 0;

		if ($request->has('error')) {
			$this->error = $request->query('error');
		} else {
			$this->error = 'None';
		}
		$this->survey = new survey($this->language);
		$this->notification = new notification();
	}
	public function initialize_call_back(Request $request)
	{
		$this->callSessionState = $request->input('CallStatus');
		$this->hangupCause = $request->input('CallStatus');
		$this->sessionId = $request->input('CallSid');
		$this->direction = $request->input('Direction');
		$this->parentID = '';
		if ($request->has('CallDuration')) {
			//coming from a call back
			$this->duration = $request->input('CallDuration');
		}
		if ($request->has('EddID')) {
			$this->eddID = $request->query('EddID');
		} else {
			$this->eddID = '';
		}
		if ($request->has('Caller')) {
			//this is from an incoming call
			$this->callerNumber = $this->ensureNumberIsFormattedCorrectly($request->input('Caller'));
		} else {
			//this is from an outgoing call
			$this->callerNumber = $this->ensureNumberIsFormattedCorrectly($request->input('Called'));
		}
		if ($request->has('CallerCountry')) {
			$this->callerCarrierName = $request->input('CallerCountry');
		} else {
			$this->callerCarrierName = '';
		}
		$this->duration = 0;
		$this->date = date('Y-m-d h:i:s');
	}
	function CallStateKeeperErrorInput($previousCallState, $EddID, $WasLastInputCorrect)
	{
		$edds = new Edd();
		$edd = $edds::where('id', $EddID);
		if ($edd->exists()) {
			$edd = $edd->first();
			$edd->previous_call_state = $this->GetPreviousCallState($previousCallState, $edd->number_delivered);
			$edd->current_call_state = $previousCallState;
			$edd->save();
		}
	}
	function GetPreviousCallState($previousCallState, $numberDelivered)
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
			case 'Question2C':
				return 'Question2';
				break;
			case 'Question3':
				if ($numberDelivered > 1) {
					return 'Question2C';
				} else {
					return 'Question2';
				}
				break;
			case 'Question4':
				return 'Question3';
				break;
			case 'Question5':
				return 'Question4';
				break;
			case 'QuestionEnd':
				return 'Question5';
				break;
		}
	}
	function scheduleACall($phone)
	{
		$callBacks = new Call_back();
		$callBack = $callBacks::where('phone', $phone);
		if ($callBack->exists()) {
			$callBack = $callBack->first();
			$callBack->status = 0;
			$callBack->save();
		} else {
			$callBacks->phone = $phone;
			$callBacks->save();
		}
	}
	function repeatCallstate(Request $request, $edd)
	{
		//check to know if the caller wants a repeat of a question
		if ($request->has('Digits')) {
			if ($request->input('Digits') == '*') {
				//a repeat of previous question is needed, make the previous state the current state
				return $edd->previous_call_state;
			} else {
				return $edd->current_call_state;
			}
		} else {
			return $edd->current_call_state;
		}
	}
}
