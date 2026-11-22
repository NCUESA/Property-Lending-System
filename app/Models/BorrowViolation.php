<?php

namespace App\Models;

use App\Enums\BorrowViolationKind;
use App\Support\BorrowSubject;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 一筆違規紀錄。只記事實，封鎖與否由 BorrowBlock 決定。
 *
 * @property int $id
 * @property BorrowViolationKind $kind
 * @property int|null $user_id
 * @property string|null $student_id
 * @property string $phone
 * @property string $borrower_name
 * @property int|null $borrow_request_id
 * @property CarbonImmutable $occurred_at
 * @property string|null $note
 */
#[Fillable([
    'kind', 'user_id', 'student_id', 'phone', 'borrower_name',
    'borrow_request_id', 'occurred_at', 'note',
])]
class BorrowViolation extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => BorrowViolationKind::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<BorrowRequest, $this> */
    public function borrowRequest(): BelongsTo
    {
        return $this->belongsTo(BorrowRequest::class);
    }

    /** @param  Builder<BorrowViolation>  $query */
    public function scopeForSubject(Builder $query, BorrowSubject $subject): void
    {
        $subject->scope($query);
    }
}
