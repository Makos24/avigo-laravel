<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Edd extends Model
{
    use HasFactory;

    public function phc(){
        return $this->hasOne(Phc::class,'id','phc_id');
    }
    public function parent(){
        return $this->hasOne(Parent_detail::class,'id','parent_id');
    }

    public function name(){
        return $this->hasOne(Name::class,'id','mother');
    }
    
}