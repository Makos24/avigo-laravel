<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Phc_notification extends Model
{
    use HasFactory;
    public function phc()
    {
        return $this->hasOne(Phc::class,'id', 'phc_id');
    }
}
