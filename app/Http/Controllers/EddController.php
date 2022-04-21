<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Edd;
use App\Models\Parent_detail;
use Illuminate\Support\Facades\DB;

class PwaController extends Controller
{
    //
    public function index()
    {
        return ['pwa' => 'PWA controller'];
    }
    public function upload(Request $request)
    {
        echo 'hello';
       // return response()->json_decode($request);
        // foreach($request->data as $data){
        //     Parent_detail::firstOrCreate(
        //         ['phone' => $data->phone],
        //         [
        //             'name' => $data->name
        //         ]
        //         );
        // }
    }
}
