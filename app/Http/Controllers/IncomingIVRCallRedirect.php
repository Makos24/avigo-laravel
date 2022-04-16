<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
/**
 * handles all incoming call redirects
 */
class IncomingIVRCallRedirect extends Controller
{
    function question1(Request $request){
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=silence" method="POST">';
        //$response .= '<Say voice="alice">Enter the number of children born. To listen to the question again press star.</Say>';
        $response .= '<Play>'.URL('').'/audio/clips/'.$request->query('language').'/error-no-response.mp3</Play>';
        $response .= '<Play>'.URL('').'/audio/ivr/'.$request->query('language').'/question-1.mp3</Play>';
        $response .= '</Gather>';
        $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        $response .= '</Response>';
        return response($response)->header('Content-type','text/xml');
    }
    function question_none(Request $request){
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Gather finishOnKey="#">';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=silence" method="POST">';
        //$response .= '<Say voice="alice">Thank you for calling Albarka. Share your good news. Has the birth occurred? If Yes, Press 1. If No, Press 2. To listen to the question again press star.</Say>';
        $response .= '<Play>'.URL('').'/audio/clips/'.$request->query('language').'/error-no-response.mp3</Play>';
        $response .= '<Play>'.URL('').'/audio/ivr/'.$request->query('language').'/question-none-error-input.mp3</Play>';
        $response .= '</Gather>';
        $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        $response .= '</Response>';
        return response($response)->header('Content-type','text/xml');
    }
    
    function question1_negative(Request $request){
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        //$response .= '<Say voice="alice">Thank you for calling Albarka, feel free to reach us again when your wife gives birth.</Say>';  
        $response .= '<Play>'.URL('').'/audio/clips/'.$request->query('language').'/error-no-response.mp3</Play>';
        $response .= '<Play>'.URL('').'/audio/ivr/'.$request->query('language').'/question-1-negative.mp3</Play>';
        $response .= '</Response>';
        return response($response)->header('Content-type','text/xml');
    }
    function question2A(Request $request){
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=silence" method="POST">';
        //$response .= '<Say voice="alice">What is the gender of the child? Press 1 for female or 2 for male. To listen to the question again press star.</Say>';
        $response .= '<Play>'.URL('').'/audio/clips/'.$request->query('language').'/error-no-response.mp3</Play>';
        $response .= '<Play>'.URL('').'/audio/ivr/'.$request->query('language').'/question-2-A.mp3</Play>';
        $response .= '</Gather>';
        $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        $response .= '</Response>';
        return response($response)->header('Content-type','text/xml');
    }
    function question2B(Request $request){
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=silence" method="POST">';
        //$response .= '<Say voice="alice">What is the gender of the first child? Press 1 for female or 2 for male. To listen to the question again press star.</Say>';
        $response .= '<Play>'.URL('').'/audio/clips/'.$request->query('language').'/error-no-response.mp3</Play>';
        $response .= '<Play>'.URL('').'/audio/ivr/'.$request->query('language').'/question-2-B.mp3</Play>';
        $response .= '</Gather>';
        $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        $response .= '</Response>';
        return response($response)->header('Content-type','text/xml');
    }
    function question2C(Request $request){
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=silence" method="POST">';
        //$response .= '<Say voice="alice">How many of these children are girls? To listen to the question again press star.</Say>';
        $response .= '<Play>'.URL('').'/audio/clips/'.$request->query('language').'/error-no-response.mp3</Play>';
        $response .= '<Play>'.URL('').'/audio/ivr/'.$request->query('language').'/question-2-C.mp3</Play>';
        $response .= '</Gather>';
        $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        $response .= '</Response>';
        return response($response)->header('Content-type','text/xml');
    }
    function question3(Request $request){
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=silence" method="POST">';
        //$response .= '<Say voice="alice">When was your child born? Press 0 for today, 1 for yesterday, 3 for three days ago, 7 for one week ago. To listen to the question again press star.</Say>';
        $response .= '<Play>'.URL('').'/audio/clips/'.$request->query('language').'/error-no-response.mp3</Play>';
        $response .= '<Play>'.URL('').'/audio/ivr/'.$request->query('language').'/question-3.mp3</Play>';
        $response .= '</Gather>';
        $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        $response .= '</Response>';
        return response($response)->header('Content-type','text/xml');
    }
    function question3_girl(Request $request)
    {
        switch($request->query('language')){
            case 'hausa':
               $response  = '<?xml version="1.0" encoding="UTF-8"?>';
                $response .= '<Response>';
                $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=silence" method="POST">';
                //$response .= '<Say voice="alice">When was your child born? Press 0 for today, 1 for yesterday, 3 for three days ago, 7 for one week ago. To listen to the question again press star.</Say>';
                $response .= '<Play>'.URL('').'/audio/clips/'.$request->query('language').'/error-no-response.mp3</Play>';
                $response .= "<Play>".URL('')."/audio/clips/'.$request->query('language').'/yaushe-ne-aka-haifi.mp3</Play>";
                $response .= "<Play>".URL('')."/audio/clips/'.$request->query('language').'/jaririan.mp3</Play>";
                $response .= "<Play>".URL('')."/audio/clips/'.$request->query('language').'/da-aka-samu-inda-yau-ne-dana-daya.mp3</Play>";
                $response .= '</Gather>';
                $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
                $response .= '</Response>';
                break;
            
        }
        return response($response)->header('Content-type','text/xml');
    }
    function question3_more_than_one_child(Request $request){
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=silence" method="POST">';
        //$response .= '<Say voice="alice">When were your children born? Press 0 for today, 1 for yesterday, 3 for three days ago, 7 for one week ago. To listen to the question again press star.</Say>';
        $response .= '<Play>'.URL('').'/audio/clips/'.$request->query('language').'/error-no-response.mp3</Play>';
        $response .= '<Play>'.URL('').'/audio/ivr/'.$request->query('language').'/question-3-more-than-one-child.mp3</Play>';
        $response .= '</Gather>';
        $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        $response .= '</Response>';
        return response($response)->header('Content-type','text/xml');
    }
    function question4(Request $request){
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=silence" method="POST">';
        //$response .= '<Say voice="alice"> When is the naming ceremony of the child? Press 0 for today, 1 for tomorrow, 3 for three days from now, 7 for one week from now. To listen to the question again press star.</Say>';
        $response .= '<Play>'.URL('').'/audio/clips/'.$request->query('language').'/error-no-response.mp3</Play>';
        $response .= '<Play>'.URL('').'/audio/ivr/'.$request->query('language').'/question-4.mp3</Play>';
        $response .= '</Gather>';
        $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        $response .= '</Response>';
        return response($response)->header('Content-type','text/xml');
    }
    function question4_girl(Request $request)
    {
        switch($this->language){
            case 'hausa':
                $response  = '<?xml version="1.0" encoding="UTF-8"?>';
                $response .= '<Response>';
                $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=silence" method="POST">';
                //$response .= '<Say voice="alice"> When is the naming ceremony of the child? Press 0 for today, 1 for tomorrow, 3 for three days from now, 7 for one week from now. To listen to the question again press star.</Say>';
                $response .= '<Play>'.URL('').'/audio/clips/'.$request->query('language').'/error-no-response.mp3</Play>';
                $response .= "<Play>".URL('')."/audio/clips/'.$request->query('language').'/yaushe-ne-ranar-sunar.mp3</Play>";
                $response .= "<Play>".URL('')."/audio/clips/'.$request->query('language').'/jaririan.mp3</Play>";
                $response .= "<Play>".URL('')."/audio/clips/'.$request->query('language').'/da-aka-samu-inda-yau-ne-dana-daya.mp3</Play>";
                $response .= '</Gather>';
                $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
                $response .= '</Response>';
                break;
            
        }
        return response($response)->header('Content-type','text/xml');
    }
    function question4_more_than_one_child(Request $request){
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=silence" method="POST">';
        //$response .= '<Say voice="alice"> When is the naming ceremony of the children? Press 0 for today, 1 for tomorrow, 3 for three days from now, 7 for one week from now. To listen to the question again press star.</Say>';
        $response .= '<Play>'.URL('').'/audio/clips/'.$request->query('language').'/error-no-response.mp3</Play>';
        $response .= '<Play>'.URL('').'/audio/ivr/'.$request->query('language').'/question-4-more-than-one-child.mp3</Play>';
        $response .= '</Gather>';
        $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        $response .= '</Response>';
        return response($response)->header('Content-type','text/xml');
    }
    function question5(Request $request){
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Gather input="dtmf" timeout="5" numDigits="1" action="'.URL('').'/api/ivr-incoming?error=silence" method="POST">';
        // $response .= '<Say voice="alice">Do you want us to share news with your contacts? Press 1 for Yes or 0 for No. To listen to the question again press star.</Say>';
        $response .= '<Play>'.URL('').'/audio/clips/'.$request->query('language').'/error-no-response.mp3</Play>';
        $response .= '<Play>'.URL('').'/audio/ivr/'.$request->query('language').'/question-5.mp3</Play>';
        $response .= '</Gather>';
        $response .= '<Say voice="alice">You seem not to be sure of your response, do take some time and call us again. Thank you.</Say>';
        $response .= '</Response>';
        return response($response)->header('Content-type','text/xml');
    }
}
