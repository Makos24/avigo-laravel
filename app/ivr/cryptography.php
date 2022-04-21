<?php
	namespace App\ivr;

    use Defuse\Crypto\Crypto;
    use Defuse\Crypto\Key;

	class cryptography{
		private Object $key;
		private Object $crypto;
		private $cryptoKey;
		function __construct()
		{
			$this->crypto=new Crypto();
			$this->key=Key::createNewRandomKey();
			$this->cryptoKey=$this->key->loadFromAsciiSafeString(env('DEFUSE_KEY'));
		}
		
		
		function encryptThis($plaintext){
			try {
				return $this->crypto::encrypt($plaintext,$this->cryptoKey);
			} catch (\Throwable $th) {
				return $th->getMessage();
			}
		}
		function decryptThis($cipher){
			try{
				$plaintext=$this->crypto::decrypt($cipher,$this->cryptoKey);
			}catch(\Exception $e){
				$plaintext= $e->getMessage();
			}
			return $plaintext;
		}
		function hashThis($plaintext){
			return password_hash($plaintext, PASSWORD_BCRYPT);
		}
		function hashThisDictionaryToken($token){
			return hash('sha512',$token);
		}
		function tokenize($sentence){
			$sentence=strtolower($sentence);
			$sentence=str_replace('-', ' ', $sentence);
			$sentence=str_replace(',', ' ', $sentence);
			$sentence=str_replace('. ', ' ', $sentence);
			$sentence=str_replace(';', ' ', $sentence);
			$sentence=str_replace(':', ' ', $sentence);
			$sentence=str_replace('/', ' ', $sentence);
			$sentence=str_replace('\\', ' ', $sentence);
			$sentence=str_replace('\'s ', ' ', $sentence);
			$sentence=str_replace('s\' ', 's', $sentence);
			$tokenizedArray=explode(' ', $sentence);
			sort($tokenizedArray);
			return $tokenizedArray;
		}
		function cleanToken($token){
			if (strlen($token)<='2') {
				return false;
			}else{
				return true;
			}
		}
		function addToDictionary($dbc,$table,$rowID,$sentence){
			//TokenID
			//tokenize the sentence
			$status=true;
			$tokenArray=$this->tokenize($sentence);
			$rowID=$this->encryptThis($rowID);
			foreach ($tokenArray as $word) {
				//check if the token meets requirement of being important terms (eg not the following and, is. we, them )
				if ($this->cleanToken($word)==true) {
					$tokenID='';
					$tokenHash=$this->hashThisDictionaryToken($word);
					$tableHash=$this->hashThisDictionaryToken($table);
					$token=$this->encryptThis($word);
					$query="SELECT SN,TokenID FROM avigo_health_dictionary WHERE TokenHash='$tokenHash'";
					$response=mysqli_query($dbc,$query);
					if($rows=mysqli_fetch_assoc($response)){
						//the token is already registered, get the token id and proceed to posting list
						$tokenID=$rows['TokenID'];
						if($response){			
							mysqli_free_result($response);
						}
					}else{
						//if the token is not yet registered add it to the dictionary
						$query="INSERT INTO `avigo_health_dictionary` (`Token`,`TokenHash`) VALUES ('$token','$tokenHash')";
						if (mysqli_query($dbc,$query)){
							$query="SELECT SN FROM avigo_health_dictionary WHERE TokenHash='$tokenHash'";
							$response=mysqli_query($dbc,$query);
							if($rows=mysqli_fetch_assoc($response)){
								if($response){			
									mysqli_free_result($response);
								}
								//update tokenID
								$tokenID='DT'.$rows['SN'];
								$query="UPDATE avigo_health_dictionary SET TokenID='$tokenID' WHERE SN='".$rows["SN"]."'";			
								if (mysqli_query($dbc,$query)==false){
									$status=false;
									break;
								}
							}else{
								$status=false;
								break;
							}
						}else{
							$status=false;
							break;
						}
					}
					//register to posting list avigo_health_posting_list
					$query="SELECT SN FROM avigo_health_posting_list WHERE TokenID='$tokenID' AND TableHash='$tableHash' AND RowID='$rowID'";
					$response=mysqli_query($dbc,$query);
					if($rows=mysqli_fetch_assoc($response)){
						//the token is already registered to posting list
						if($response){			
							mysqli_free_result($response);
						}
					}else{
						//if the token is not yet registered add it to the dictionary
						$query="INSERT INTO `avigo_health_posting_list` (`TokenID`,`TableHash`,`RowID`) VALUES ('$tokenID','$tableHash','$rowID')";
						if (mysqli_query($dbc,$query)==false){
							$status=false;
							break;
						}
					}
				}
			}
			unset($word);
			return $status;							
		}

		function searchThisQuery($dbc,$queryField,$searchTable,$searchToken){
			$searchTokenHash=$this->hashThisDictionaryToken(strtolower($searchToken));
			//search for the search token from the dictionary
			$associatedRowIDs='';
			$Dictionaryquery="SELECT SN,TokenID FROM avigo_health_dictionary WHERE TokenHash='$searchTokenHash'";
			$DictionaryResponse=mysqli_query($dbc,$Dictionaryquery);
			if($Dictionaryrows=mysqli_fetch_assoc($DictionaryResponse)){
				if($DictionaryResponse){			
					mysqli_free_result($DictionaryResponse);
				}
				//get the associated token id for this search token
				//$postingQuery="SELECT SN FROM avigo_health_posting_list WHERE TokenID='$tokenID' AND TableHash='$tableHash' AND RowID='$rowID'";
				$tokenID=$Dictionaryrows['TokenID'];
				//get all the posting list associated with this dictionary id for the search token
				$tableHash=$this->hashThisDictionaryToken($searchTable);
				$postingQuery="SELECT RowID FROM avigo_health_posting_list WHERE TableHash='$tableHash' AND TokenID='$tokenID'";
				$postingResponse=mysqli_query($dbc,$postingQuery);
				$associatedRowIDs='(';
				$count=0;
				while($postingrows=mysqli_fetch_assoc($postingResponse)){
					if ($count!=0) {
						$associatedRowIDs.=' OR ';
					}
					$associatedRowIDs.= $queryField ."='".$this->decryptThis($postingrows['RowID'])."'";
					$count++;
				}
				$associatedRowIDs.=')';
				if($postingResponse){			
					mysqli_free_result($postingResponse);
				}
				return $associatedRowIDs;
			}else{
				if ($associatedRowIDs=='') {
					$associatedRowIDs='(0=\'1\')';
				}
				return $associatedRowIDs;
			}
		}
	}

	
?>