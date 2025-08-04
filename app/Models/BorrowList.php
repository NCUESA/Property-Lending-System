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
    protected $appends = ['status']; // 自動加到 JSON 輸出

    public function borrowItems()
    {
        return $this->hasMany(BorrowItem::class, 'borrow_id', 'id');
    }

    public function getStatusAttribute()
    {
        $items = $this->borrowItems;

        $hasBorrowed = $items->contains(fn($i) => $i->status == 1);
        $hasReturned = $items->every(fn($i) => $i->status == 3);
        $hasRejected = $items->contains(fn($i) => $i->status == 0);

        if ($hasBorrowed) return 1;      // 外借中
        if ($hasReturned) return 3;      // 已全部歸還
        if ($hasRejected) return 0;      // 系統拒絕
        return 2;                        // 已填單 (但還沒借出)
    }
}
