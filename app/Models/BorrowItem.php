<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BorrowItem extends Model
{
    use HasFactory;

    protected $table = 'borrow_item';

    protected $fillable = [
        'borrow_id',
        'property_id',
        'status',
        'returned_date'

    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id', 'ssid');
    }

    /**
     * Get the borrow list that owns the borrow item.
     */
    public function borrowList()
    {
        return $this->belongsTo(BorrowList::class, 'borrow_id', 'id');
    }
}
