<?php

namespace App\Models;

use Carbon\Carbon;
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
        $now = now()->startOfDay(); // 將當前時間設為當天 00:00:00

        // 判斷逾期：有任何物品尚未歸還且預計歸還日 < 當天 00:00:00
        $hasOverdue = $items->contains(function ($i) use ($now) {
            return $i->status !== 3
                && $i->returned_date
                && $now->gt(Carbon::parse($i->returned_date)->startOfDay());
        });

        if ($hasOverdue) return 4; // overdue

        $hasBorrowed = $items->contains(fn($i) => $i->status == 1);
        $hasReturned = $items->contains(fn($i) => $i->status == 3);
        $hasRejected = $items->contains(fn($i) => $i->status == 0);

        if ($hasBorrowed) return 1;      // 外借中
        if ($hasReturned) return 3;      // 已全部歸還
        if ($hasRejected) return 0;      // 系統拒絕
        return 2;                        // 已填單 (但還沒借出)
    }

    public function scopeWithStatus($query, $status)
    {
        // 先抓出資料，再用 Collection 過濾 status
        return $query->get()->filter(fn($borrow) => $borrow->status == $status);
    }
}
