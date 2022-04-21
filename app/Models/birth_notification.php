<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Edd;

class Birth_notification extends Model
{
    use HasFactory;
    public function edd(){
        return $this->hasOne(Edd::class,'id','edd_id');
    }
}
