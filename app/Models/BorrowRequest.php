<?php

namespace App\Models;

use Database\Factories\BorrowRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * 借用單。只存發生過的事實，狀態請看 overview（VIEW borrow_request_overviews）。
 *
 * @property int $id
 * @property int $location_id
 * @property int|null $user_id
 * @property string $borrower_department
 * @property string $borrower_name
 * @property string $phone
 * @property string $email
 * @property Carbon $borrow_date
 * @property Carbon $due_date
 * @property bool $agreed_terms
 * @property int|null $lent_by
 * @property Carbon|null $lent_at
 * @property bool $id_card_taken
 * @property bool $deposit_taken
 * @property int|null $deposit_amount
 * @property int|null $deposit_box_no
 * @property int|null $returned_by
 * @property Carbon|null $returned_at
 * @property bool $id_card_returned
 * @property bool $deposit_returned
 * @property string|null $remark
 * @property string|null $legacy_lender_name
 * @property string|null $legacy_returner_name
 * @property Carbon $created_at
 */
#[Fillable([
    'location_id', 'user_id', 'borrower_department', 'borrower_name', 'phone', 'email',
    'borrow_date', 'due_date', 'agreed_terms',
    'lent_by', 'lent_at', 'id_card_taken', 'deposit_taken', 'deposit_amount', 'deposit_box_no',
    'returned_by', 'returned_at', 'id_card_returned', 'deposit_returned',
    'remark', 'legacy_lender_name', 'legacy_returner_name',
])]
class BorrowRequest extends Model
{
    /** @use HasFactory<BorrowRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'borrow_date' => 'date',
            'due_date' => 'date',
            'agreed_terms' => 'boolean',
            'lent_at' => 'datetime',
            'returned_at' => 'datetime',
            'id_card_taken' => 'boolean',
            'deposit_taken' => 'boolean',
            'deposit_amount' => 'integer',
            'id_card_returned' => 'boolean',
            'deposit_returned' => 'boolean',
        ];
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * 登入借用時的借用人帳號。
     *
     * @return BelongsTo<User, $this>
     */
    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<BorrowRequestItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BorrowRequestItem::class);
    }

    /**
     * 即時計算的狀態與明細統計。
     *
     * @return HasOne<BorrowRequestOverview, $this>
     */
    public function overview(): HasOne
    {
        return $this->hasOne(BorrowRequestOverview::class, 'id');
    }

    /** @return BelongsTo<User, $this> */
    public function lender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lent_by');
    }

    /** @return BelongsTo<User, $this> */
    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }
}
