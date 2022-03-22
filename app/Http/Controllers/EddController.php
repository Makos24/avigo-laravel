<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Edd;
use App\Models\Parent_detail;
use Illuminate\Support\Facades\DB;

class EddController extends Controller
{
    //
    function parent(){
        $edd=new Edd();
        return $edd::with('parent','phc')->get();
        //$edd=db::table('parents','')->get();
        //return $edd;
    }
    function state($parent_id){

    }
}
