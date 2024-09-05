<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResponsiblePeople extends Model
{
    use HasFactory;

    protected $table = 'responsible_person';

    protected $fillable = [
        'name',
        'status'
    ];
}
