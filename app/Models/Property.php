<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    /** Specify the database name 
     *  (Laravel will automatically infers the database name)
     * 
     * 2024-08-29
     * */
    protected $table = 'property';
    protected $fillable = [
        'ssid',
        'class',
        'name',
        'second_name',
        'order_number',
        'price',
        'department',
        'depositary',
        'belong_place',
        'get_day',
        'format',
        'remark',
        'enable_lending',
        'lending_status',
        'property_status',
        'img_url',
        'school_property'
    ];

    public function borrowItems()
    {
        return $this->hasMany(BorrowItem::class, 'property_id', 'ssid');
    }

}
