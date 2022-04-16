<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Edd;
use App\ivr\call_thread;
/**
 * handles incoming calls
 */
class IncomingIVRCall extends Controller
{
    function ivrIncoming(Request $request)
    {
        $callThread = new call_thread();
        $callThread->initialize_call($request);
        $callThread->saveCallBehaviour('IncomingIVRCall/ivrIncoming');
        return response($callThread->handleIvrSession($request))->header('Content-type','text/xml');        
    }
}
