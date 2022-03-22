<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Edd;
use App\ivr\call_thread;

class IncomingIVRCall extends Controller
{
    //
    function ivrIncoming(Request $request)
    {
        $callThread = new call_thread();
        $callThread->initialize_call($request);
        $callThread->saveCallBehaviour('inbound-voice-call-callback-uri/index.php');
        //return $callThread->instantiateSurvey($request);
        return $callThread->handleIvrSession($request);
    }
}
