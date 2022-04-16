<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parent_notification extends Model
{
    use HasFactory;
    public function language()
    {
        return $this->hasOne(Language::class,'id', 'language_id');
    }
}
