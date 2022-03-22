<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parent_detail extends Model
{
    use HasFactory;
    protected $table = 'parents';
    public function state(){
        return $this->hasOne(State::class,'id','state_id');
    }
    public function language()
    {
        return $this->hasOne(Language::class,'id', 'language_id');
    }
}
