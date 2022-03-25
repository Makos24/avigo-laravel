<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EddController;
use App\Http\Controllers\IncomingIVRCall;
use App\Http\Controllers\RegistrationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/', function(){
    return ['avigo-api' => 'Working'];
});

Route::match(['get', 'post'], 'ivr-incoming', [IncomingIVRCall::class,'ivrIncoming']);
Route::get('/phc', [EddController::class,'phc']);
Route::get('/parent', [EddController::class,'parent']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::get('/pwa', [RegistrationController::class, 'index']);
Route::post('/upload-pwa', [RegistrationController::class, 'upload']);
