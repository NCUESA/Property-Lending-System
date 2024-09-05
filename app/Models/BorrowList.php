<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BorrowList extends Model
{
    use HasFactory;

    protected $table = 'borrowlist';

    protected $fillable = [
        'understand',
        'borrow_place',
        'borrow_department',
        'borrow_person_name',
        'phone',
        'email',
        'borrow_date',
        'returned_date',

        'sa_lending_person_name',
        'sa_lending_date',
        'sa_id_take',
        'sa_deposit_take',
        'sa_id_deposit_box_number',
        'sa_returned_date',
        'sa_id_returned',
        'sa_deposit_returned',
        'sa_remark'
    ];

    public function borrowItems()
    {
        return $this->hasMany(BorrowItem::class, 'borrow_id', 'id');
    }
}
