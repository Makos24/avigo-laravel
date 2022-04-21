<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parent_Notification extends Model
{
    use HasFactory;
    protected $fillable = ['edd_id', 'parent_id', 'phone', 'mother'];
}
