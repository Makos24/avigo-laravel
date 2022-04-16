<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EddController;
use App\Http\Controllers\IncomingIVRCall;
use App\Http\Controllers\IncomingIVRCallRedirect;
use App\Http\Controllers\OutgoingCall;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API! Question4Girl
|
*/
Route::match(['get', 'post'], 'ivr-incoming/redirects/question1', [IncomingIVRCallRedirect::class,'question1']);
Route::match(['get', 'post'], 'ivr-incoming/redirects/question1-negative', [IncomingIVRCallRedirect::class,'question1_negative']);
Route::match(['get', 'post'], 'ivr-incoming/redirects/question2A', [IncomingIVRCallRedirect::class,'question2A']);
Route::match(['get', 'post'], 'ivr-incoming/redirects/question2B', [IncomingIVRCallRedirect::class,'question2B']);
Route::match(['get', 'post'], 'ivr-incoming/redirects/question-2-C', [IncomingIVRCallRedirect::class,'question2C']);
Route::match(['get', 'post'], 'ivr-incoming/redirects/question3', [IncomingIVRCallRedirect::class,'question3']);
Route::match(['get', 'post'], 'ivr-incoming/redirects/question3-girl', [IncomingIVRCallRedirect::class,'question3_girl']);
Route::match(['get', 'post'], 'ivr-incoming/redirects/question3-more-than-one-child', [IncomingIVRCallRedirect::class,'question3_more_than_one_child']);
Route::match(['get', 'post'], 'ivr-incoming/redirects/question4', [IncomingIVRCallRedirect::class,'question4']);
Route::match(['get', 'post'], 'ivr-incoming/redirects/question4-girl', [IncomingIVRCallRedirect::class,'question4_girl']);
Route::match(['get', 'post'], 'ivr-incoming/redirects/question4-more-than-one-child', [IncomingIVRCallRedirect::class,'question4_more_than_one_child']);
Route::match(['get', 'post'], 'ivr-incoming/redirects/question5', [IncomingIVRCallRedirect::class,'question5']);
Route::match(['get', 'post'], 'ivr-incoming/redirects/question-none', [IncomingIVRCallRedirect::class,'question_none']);

Route::match(['get', 'post'], 'ivr-incoming', [IncomingIVRCall::class,'ivrIncoming']);

Route::match(['get', 'post'], 'birth-report-reminder', [OutgoingCall::class,'birthReportReminder']);
Route::match(['get', 'post'], 'notify-contacts', [OutgoingCall::class,'notifyContacts']);
Route::match(['get', 'post'], 'edd-registration-notification', [OutgoingCall::class,'eddRegistrationNotification']);

Route::match(['get', 'post'], 'ivr-incoming-callback', [CallBack::class,'ivrIncomingCallBack']);
Route::match(['get', 'post'], 'notify-contacts-callback', [CallBack::class,'notifyContactsCallBack']);
Route::match(['get', 'post'], 'birth-report-reminder-callback', [CallBack::class,'birthReportReminderCallback']);
Route::match(['get', 'post'], 'edd-registration-notification-callback', [CallBack::class,'eddRegistrationNotificationCallback']);
Route::get('/phc', [EddController::class,'phc']);
Route::get('/parent', [EddController::class,'parent']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
