<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parent_detail extends Model
{
    use HasFactory;
    protected $table = 'parents';

    protected $guarded = ['id'];
    public function state(){
        return $this->hasOne(State::class,'id','state_id');
    }
    public function language()
    {
        return $this->hasOne(Language::class,'id', 'language_id');
    }

    public function edd()
    {
        return $this->hasMany(Edd::class, 'parent_id');
    }

    public function birth_contacts()
    {
        return $this->hasMany(Birth_contact::class, 'parent_id');
    }

    public function parent_notifications()
    {
        return $this->hasMany(Parent_Notification::class, 'parent_id');
    }
    
}
