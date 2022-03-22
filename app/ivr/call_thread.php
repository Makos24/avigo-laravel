<?php

namespace App\ivr;

use App\Models\Edd;
use App\Models\Call_behaviour;
use App\Models\Language;
use App\Models\Unauthorized_request;
use App\Models\Parent_detail;
use Illuminate\Http\Request;
use App\ivr\survey;

/**
 * 
 */
class call_thread
{
	private string $nameofuser;
	private string $callSessionState; //holds the state of the Twilio call, whether ringing or in progress
	private string $sessionId; //holds the session id of the call from Twilio
	private string $callerNumber; //phone number of the caller as provided by Twilio API
	private string $callerCarrierName; //the country of the caller
	private string $direction; //the direction of the call incoming or out-going
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
	private $birthCounter;
	private $numberDelivered;
	private $phcPhone;
	private $phcEmail;
	private $phcID;
	private $wasLastInputCorrect;
	function __construct()
	{
	}
	public function getCaller()
	{
		echo $this->callerNumber;
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
			return $parent::with('language')->first()->language->name;
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
		if ($this->callerExists()) {
			$edds = new Edd();
			$edd = $edds::with('name')->where('parent_id', $this->parentID)
				->where('survey_completed', 0)
				->orderByDesc('id')->get();
			$numberOfEDDs = $edd->count();
			//get the Edd info and current call state
			//check to see how many record of edd are left unreported
			if ($numberOfEDDs > 0) {
				if ($numberOfEDDs == 1) {
					//if the returned number of EDDs for this family is one
					$response = $this->survey->mainEntryToSurvey($request, $edd->first(), $this->sessionId);
				} else {
					//if the number of EDDs for this family is more than one, get the mother's name and use that to differentiate
					//form the message
					$response = $this->survey->composeQuestionForMothersNameSelection($request, $edd, $this->callerNumber);
				}
				//enter call behaviour
				$this->survey->setCallBehaviour($this->sessionId, $this->direction, $this->callSessionState, $this->date, $this->callerNumber, $this->eddID, $this->parentID, 'inbound-voice-call-callback-uri/index.php');
			} else {
				//end call
				$response = $this->survey->NoSessionRemainingMessage();
			}
		} else {
			//end call. This phone number is not registered 
			//if the phone number is not found in the database then record the number and quit
			$unauthorized_request = new Unauthorized_request();
			$unauthorized_request->sessionid = $this->sessionId;
			$unauthorized_request->phone = $this->callerNumber;
			$unauthorized_request->service_code = $this->callerCarrierName;
			$unauthorized_request->save();
			return $this->survey->UnAuthorisedUserMessage();
		}
	}
	//handles the IVR session checks if the session exists ,gets the next question or gets an entry point into the survey
	public function handleIvrSession(Request $request)
	{
		if ($this->sessionIdExists()) {
			$edds = new Edd();
			$edd = $edds::with('phc')->where('sessionid', $this->sessionId)->first();
			$this->numberOfMales = $edd->male;
			$this->numberOfFemales = $edd->female;
			$this->parentID = $edd->parent_id;
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
			$this->currentCallState = $this->survey->repeatCallstate($request, $edd);
			//enter call behaviour
			$this->survey->setCallBehaviour($this->sessionId, $this->direction, $this->callSessionState, $this->date, $this->callerNumber, $this->eddID, $this->parentID, 'inbound-voice-call-callback-uri/index.php');
			return $this->retrieveSurveyQuestions($request, $edd, $this->currentCallState);
		} else {
			return $this->instantiateSurvey($request);
		}
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
				$dtmfDigits = $request->input('Digits');
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
				}
				break;
			case 'Question2':
				if ($request->input('Digits') == '*') {
					//a repeat of previous question is needed, make the previous state the current state
					$response = $this->survey->Question1();
				} else {
					//check if the input is correct or not(that is the number of children is neither alphabet nor 0)
					if (is_numeric($request('Digits')) == true and $request('Digits') > 0) {
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
						$edd->previous_call_state='Question2';
						$edd->current_call_state=$Question;
						$edd->end_time=$this->date;
						$edd->number_delivered=$this->numberDelivered;
						$edd->was_last_input_correct=1;
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
				}
				break;
			case 'Question2C':
				if ($request->input('Digits') == '*') {
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
						$edd->previous_call_state='Question2C';
						$edd->current_call_state=$Question;
						$edd->end_time=$this->date;
						$edd->male=$dtmfDigits;
						$edd->was_last_input_correct=1;
						if ($edd->save()) {
							//ask of the number of female children
							$response = $this->survey->Question2C();
						} else {
							// Compose the response
							$response = $this->survey->ErrorMessage();
						}
					} elseif (is_numeric($dtmfDigits) == true and $dtmfDigits > 0 and $dtmfDigits < 10 and $dtmfDigits == $this->numberDelivered) {
						$Question = 'Question4';
						$edd->previous_call_state='Question3';
						$edd->current_call_state=$Question;
						$edd->end_time=$this->date;
						$edd->male=$dtmfDigits;
						$edd->female=0;
						$edd->was_last_input_correct=1;
						if ($edd->save()) {
							//ask of the day of delivery
							$response = $this->survey->Question3();
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
				}
				break;
			case 'Question3':

				break;
			default:
				break;
		}
		/*if ($currentCallState == 'None' or $currentCallState == '') {
			// Compose the response        
			//update the edd sessionID field and current call state to first question
			$query = "UPDATE `edd` SET `PreviousCallState`='None',`CurrentCallState`='Question1',`StartTime`='$date',`EndTime`='$date',WasLastInputCorrect='1' WHERE `EddID`='" . $rows['EddID'] . "'";
			if (mysqli_query($dbc, $query)) {
				// Compose the response
				//introduce avigo health and ask if birth has occurred
				$response = QuestionNone($language);
			} else {
				// Compose the response
				$response = ErrorMessage($language);
			}
		} elseif ($currentCallState == 'Question1') {
			//update response to previous question
			//update the edd sessionID field and current call state to second question
			if ($_POST['Digits'] == '*') {
				//a repeat of previous question is needed, make the previous state the current state
				$response = QuestionNone($language);
			} else {
				$dtmfDigits = $_POST['Digits'];
				//check for erroneous input
				if ($dtmfDigits == 1 or $dtmfDigits == 2) {
					//get whether there was a birth        
					//update previous response to questionNone which is whether birth has occurred?
					if ($dtmfDigits == 1) {
						$dtmfDigits = 1;
						//next question to ask is gender of children or how many children are male (Question2)
						$query = "UPDATE `edd` SET `PreviousCallState`='Question1',`CurrentCallState`='Question2',`EndTime`='$date',`HasBirthOccurred`='$dtmfDigits',WasLastInputCorrect='1' WHERE `EddID`='" . $rows['EddID'] . "'";
					} else {
						$dtmfDigits = 0;
						//roll back to the last question 
						//next question to ask how many children were born (Question1)
						$query = "UPDATE `edd` SET `PreviousCallState`='None',`CurrentCallState`='Question1',`EndTime`='$date',`HasBirthOccurred`='$dtmfDigits',WasLastInputCorrect='1' WHERE `EddID`='" . $rows['EddID'] . "'";
					}
					if (mysqli_query($dbc, $query)) {
						if ($dtmfDigits == 1) {
							// Compose the response
							//ask of the number of children born?
							$response = Question1($language);
						} else {
							// Compose the response
							//end the conversation, since birth has not occurred yet
							$response = Question1Negative($language);
						}
					} else {
						// Compose the response
						$response = ErrorMessage($language);
					}
				} else { //ensure call state is correct
					//input is erroneous
					//check if this is the second time this input is not correct
					if ($error == 'silence' or $error == 'input') {
						// end call and ask the user to redial
						$response = EndCallAndAskaRecall($language);
					} else {
						//proceed to repeat call
						$error = 'input';
						$response = QuestionNoneErrorInput($language, $error);
					}
				}
			}
		} elseif ($currentCallState == 'Question2') {
			if ($_POST['Digits'] == '*') {
				//a repeat of previous question is needed, make the previous state the current state
				$response = Question1($language);
			} else {
				//check if the input is correct or not(that is the number of children is neither alphabet nor 0)
				if (is_numeric($_POST['Digits']) == true and $_POST['Digits'] > 0) {
					//the input is a number and is not 0   
					$NumberDelivered = $_POST['Digits'];
					$Question = '';
					if ($NumberDelivered == '1') {
						//next question to ask is when was (were) the child(ren) born?
						$Question = 'Question3';
					} else {
						//next question to ask is How many children are girls? 
						$Question = 'Question2C';
					}
					//update the previous question which is number of children born if the returned digit is not a *
					$query = "UPDATE `edd` SET `PreviousCallState`='Question2',`CurrentCallState`='$Question',`EndTime`='$date',`NumberDelivered`='$NumberDelivered',WasLastInputCorrect='1' WHERE `EddID`='" . $rows['EddID'] . "'";
					if (mysqli_query($dbc, $query)) {
						if ($NumberDelivered == '1') { //if only one child was born
							// Compose the response
							//ask for the gender of the child, 1 for female and 2 for male
							$response = Question2A($language);
						} else { //if more than one child was born
							// Compose the response
							//ask how many are boys
							$response = Question2B($language);
						}
					} else {
						// Compose the response
						$response = ErrorMessage($language);
					}
				} else {
					//inform user of the wrong question and ask for a correct input
					//check if this is the second time this input is not correct
					if ($error == 'silence' or $error == 'input') {
						// end call and ask the user to redial
						$response = EndCallAndAskaRecall($language);
					} else {
						//proceed to repeat call
						$error = 'input';
						$response = Question1ErrorInput($language, $error);
					}
				}
			}
		} elseif ($currentCallState == 'Question2C') {
			if ($_POST['Digits'] == '*') {
				//repeat previously asked question
				if ($NumberDelivered == '1') { //if only one child was born
					// Compose the response
					//ask for the gender of the child, 1 for female and 2 for male
					$response = Question2A($language);
				} else { //if more than one child was born
					// Compose the response
					//ask how many are boys
					$response = Question2B($language);
				}
			} else {
				//update response to previous question
				//update the edd sessionID field and current call state to second question        
				//get the number of female children born
				$dtmfDigits = $_POST['Digits'];
				$Question = 'Question3';
				if (is_numeric($dtmfDigits) == true and $dtmfDigits > 0 and $dtmfDigits < 10 and $dtmfDigits < $NumberDelivered) {
					$query = "UPDATE `edd` SET `PreviousCallState`='Question2C',`CurrentCallState`='$Question',`Male`='$dtmfDigits',`EndTime`='$date',WasLastInputCorrect='1' WHERE `EddID`='" . $rows['EddID'] . "'";
					if (mysqli_query($dbc, $query)) {
						//ask of the number of female children
						$response = Question2C($language);
					} else {
						// Compose the response
						$response = ErrorMessage($language);
					}
				} elseif (is_numeric($dtmfDigits) == true and $dtmfDigits > 0 and $dtmfDigits < 10 and $dtmfDigits == $NumberDelivered) {
					$Question = 'Question4';
					$query = "UPDATE `edd` SET `PreviousCallState`='Question3',`CurrentCallState`='$Question',`Male`='$dtmfDigits',`Female`=0,`EndTime`='$date',WasLastInputCorrect='1' WHERE `EddID`='" . $rows['EddID'] . "'";
					if (mysqli_query($dbc, $query)) {
						//ask of the day of delivery
						$response = Question3($language);
					} else {
						// Compose the response
						$response = ErrorMessage($language);
					}
				} else {
					//the last input to question 2B was out of range, repeat question again
					//check if this is the second time this input is not correct
					if ($error == 'silence' or $error == 'input') {
						// end call and ask the user to redial
						$response = EndCallAndAskaRecall($language);
					} else {
						//proceed to repeat call
						$error = 'input';
						$response = Question2BErrorInput($language, $error);
					}
				}
			}
		} elseif ($currentCallState == 'Question3') {
			if ($_POST['Digits'] == '*') {
				//repeat previous question
				if ($NumberDelivered == 1) {
					//ask what is the gender of the child
					$response = Question2A($language);
				} else {
					//ask how many children are female
					$response = Question2C($language);
				}
			} else {
				$dtmfDigits = $_POST['Digits'];
				//check the previous question to now how to treat http input
				if ($previousCallState == 'Question2C') {
					if (is_numeric($dtmfDigits) and $dtmfDigits > 0 and $dtmfDigits < 10 and ($dtmfDigits + $numberOfMales) == $NumberDelivered) {
						$query = "UPDATE `edd` SET `PreviousCallState`='Question3',`CurrentCallState`='Question4',`Female`='$dtmfDigits',`EndTime`='$date',WasLastInputCorrect='1' WHERE `EddID`='" . $rows['EddID'] . "'";
						if (mysqli_query($dbc, $query)) {
							// Compose the response
							$response = Question3MoreThanOneChild($language);
						} else {
							// Compose the response
							$response = ErrorMessage($language);
						}
					} else {
						//the last input to question 2B was out of range, repeat question again
						//check if this is the second time this input is not correct
						if ($error == 'silence' or $error == 'input') {
							// end call and ask the user to redial
							$response = EndCallAndAskaRecall($language);
						} else {
							//proceed to repeat call
							$error = 'input';
							$response = Question2CErrorInput($language, $error);
						}
					}
				} elseif ($previousCallState == 'Question2') {
					//this came from question 2 (that is a 1 for female or 2 for male)
					if ($dtmfDigits == 1 or $dtmfDigits == 2) {
						//check the gender of the child
						$Female = 0;
						$Male = 0;
						if ($_POST['Digits'] == 1) {
							//the child is female
							$Female = 1;
						} elseif ($_POST['Digits'] == 2) {
							//the child is male
							$Male = 1;
						}
						$query = "UPDATE `edd` SET `PreviousCallState`='Question3',`CurrentCallState`='Question4',`Female`='$Female',`Male`='$Male',`EndTime`='$date',WasLastInputCorrect='1' WHERE `EddID`='" . $rows['EddID'] . "'";
						if (mysqli_query($dbc, $query)) {
							// Compose the response
							$response = Question3($language);
						} else {
							// Compose the response
							$response = ErrorMessage($language);
						}
					} else {
						//re-ask question 2A due to errorneous input
						//check if this is the second time this input is not correct
						if ($error == 'silence' or $error == 'input') {
							// end call and ask the user to redial
							$response = EndCallAndAskaRecall($language);
						} else {
							//proceed to repeat call
							$error = 'input';
							$response = Question2AErrorInput($language, $error);
						}
					}
				}
			}
		} elseif ($currentCallState == 'Question4') {
			//update response to previous question
			//update the edd sessionID field and current call state to second question        
			//get whether there was a birth
			$dtmfDigits = $_POST['Digits'];
			$dod = getDatePast($dtmfDigits);
			if ($_POST['Digits'] == '*') {
				if ($NumberDelivered == 1) {
					$response = Question3($language);
				} else {
					$response = Question3MoreThanOneChild($language);
				}
			} else {
				if ($dtmfDigits >= 0 and $dtmfDigits <= 7) {
					$query = "UPDATE `edd` SET `PreviousCallState`='Question4',`CurrentCallState`='Question5',`DoD`='$dod',`EndTime`='$date',WasLastInputCorrect='1' WHERE `EddID`='" . $rows['EddID'] . "'";
					if (mysqli_query($dbc, $query)) {
						//insert gender of child
						if ($NumberDelivered == 1) {
							$response = Question4($language);
						} else {
							$response = Question4MoreThanOneChild($language);
						}
					} else {
						// Compose the response
						$response = ErrorMessage($language);
					}
				} else {
					if ($NumberDelivered == 1) {
						//check if this is the second time this input is not correct
						if ($error == 'silence' or $error == 'input') {
							// end call and ask the user to redial
							$response = EndCallAndAskaRecall($language);
						} else {
							//proceed to repeat call
							$response = Question3ErrorInput($language, $error);
						}
					} else {
						//check if this is the second time this input is not correct
						if ($error == 'silence' or $error == 'input') {
							// end call and ask the user to redial
							$response = EndCallAndAskaRecall($language);
						} else {
							//proceed to repeat call
							$error = 'input';
							$response = Question3MoreThanOneChildErrorInput($language, $error);
						}
					}
				}
			}
		} elseif ($currentCallState == 'Question5') {
			//update response to previous question
			//update the edd sessionID field and current call state to second question        
			//get whether there was a birth
			$dtmfDigits = $_POST['Digits'];
			$doc = getDateAhead($dtmfDigits);
			if ($_POST['Digits'] == '*') {
				//repeat previous question that is question 4
				if ($NumberDelivered == 1) {
					$response = Question4($language);
				} else {
					$response = Question4MoreThanOneChild($language);
				}
			} else {
				//update health facilities notifications
				updatePhcNotification($dbc, $FacID, $phcPhone, $phcEmail, $NumberDelivered);
				//update call status
				if ($dtmfDigits >= 0 and $dtmfDigits <= 7) {
					$query = "UPDATE `edd` SET `PreviousCallState`='Question5',`CurrentCallState`='QuestionEnd',`DoC`='$doc',`EndTime`='$date',WasLastInputCorrect='1' WHERE `EddID`='" . $rows['EddID'] . "'";
					if (mysqli_query($dbc, $query)) {
						//insert gender of child
						$response = Question5($language);
					} else {
						// Compose the response
						$response = ErrorMessage($language);
					}
				} else {
					if ($NumberDelivered == 1) {
						//check if this is the second time this input is not correct
						if ($error == 'silence' or $error == 'input') {
							// end call and ask the user to redial
							$response = EndCallAndAskaRecall($language);
						} else {
							//proceed to repeat call
							$error = 'input';
							$response = Question4ErrorInput($language, $error);
						}
					} else {
						//check if this is the second time this input is not correct
						if ($error == 'silence' or $error == 'input') {
							// end call and ask the user to redial
							$response = EndCallAndAskaRecall($language);
						} else {
							//proceed to repeat call
							$error = 'input';
							$response = Question4MoreThanOneChildErrorInput($language, $error);
						}
					}
				}
			}
		} elseif ($currentCallState == 'QuestionEnd') {
			//update response to previous question
			//update the edd sessionID field and current call state to second question        
			$dtmfDigits = $_POST['Digits'];
			if ($_POST['Digits'] == '*') {
				//repeat question 5
				$response = Question5($language);
			} else {
				if ($_POST['Digits'] == 2 or $_POST['Digits'] == 1) {
					$query = "UPDATE `edd` SET `PreviousCallState`='QuestionEnd',`CurrentCallState`='SessionEnd',`Notif_Auth`='$dtmfDigits',`EndTime`='$date',WasLastInputCorrect='1',SurveyCompleted='1' WHERE `EddID`='" . $rows['EddID'] . "'";
					if (mysqli_query($dbc, $query)) {
						$EddID = $rows['EddID'];
						//set contact notification if allowed
						if ($_POST['Digits'] == 1) {
							//add contacts to notification table
							setUpContactNotification($dbc, $parentID, $EddID);
							//call notification script here
							$relativePath = __DIR__;
							require_once('notify-contacts.php');
						}
						//call notification script here
						$response = Question6End($language);
					} else {
						// Compose the response
						$response = ErrorMessage($language);
					}
				} else {
					//check if this is the second time this input is not correct
					if ($error == 'silence' or $error == 'input') {
						// end call and ask the user to redial
						$response = EndCallAndAskaRecall($language);
					} else {
						//proceed to repeat call
						$error = 'input';
						$response = Question5ErrorInput($language, $error);
					}
				}
			}
		} else {
			$response = NoSessionRemainingMessage($language);
		}*/
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
			$call->end_date = $this->date;
			$call->final_call_status = $this->callSessionState;
			$call->save();
		} else {
			$calls->sessionid = $this->sessionId;
			$calls->phone = $this->callerNumber;
			$calls->start_date = $this->date;
			$calls->end_date = $this->date;
			$calls->uri = $uri;
			$calls->call_direction = $this->direction;
			$calls->final_call_status = $this->callSessionState;
			$calls->save();
		}
	}
	/*this method initializes the call thread with the call parameters as obtained from Twilio, first parameter $POST is the $_POST variable and second parameter $GET is the $_GET variable from the Twilio call*/
	public function initialize_call(Request $request)
	{
		$this->callSessionState = $request->input('CallStatus');
		$this->sessionId = $request->input('CallSid');
		$this->callerNumber = $this->ensureNumberIsFormattedCorrectly($request->input('Caller'));
		if ($request->has('CallerCountry')) {
			$this->callerCarrierName = $request->input('CallerCountry');
		} else {
			$this->callerCarrierName = '';
		}
		$this->direction = $request->input('Direction');
		$this->date = date('Y-m-d h:i:s');
		$this->currentCallState = '';
		$this->parentID = '';
		$this->eddID = '';
		$this->numberOfMales = 0;
		$this->numberOfFemales = 0;
		if ($request->has('MothersName')) {
			$this->mothersName = true;
			$this->parentID = $request->query('ParentID');
		} else {
			$this->mothersName = false;
		}
		$this->language = $this->parentLanguage($this->parentID);
		if ($request->has('error')) {
			$this->error = $request->query('error');
		} else {
			$this->error = 'None';
		}
		$this->survey = new survey($this->language);
	}
}
