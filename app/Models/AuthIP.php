<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthIP extends Model
{
    use HasFactory;
    protected $table = 'authip';
    protected $fillable = [
        'id',
        'ip',
        'describe',
        'auth_level'
    ];

}
