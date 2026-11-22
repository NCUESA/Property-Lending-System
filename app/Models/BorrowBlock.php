<?php

namespace App\Models;

use App\Support\BorrowSubject;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 暫時禁止借用。由 RecordBorrowViolation 在違規達上限時自動建立，管理員可提前解封。
 *
 * @property int $id
 * @property string|null $student_id
 * @property string $phone
 * @property string $borrower_name
 * @property string $reason
 * @property CarbonImmutable $blocked_until
 * @property int|null $created_by
 * @property CarbonImmutable|null $lifted_at
 * @property int|null $lifted_by
 * @property string|null $lift_reason
 * @property CarbonImmutable|null $adjusted_at
 * @property int|null $adjusted_by
 * @property string|null $adjust_reason
 */
#[Fillable([
    'student_id', 'phone', 'borrower_name', 'reason',
    'blocked_until', 'created_by', 'lifted_at', 'lifted_by', 'lift_reason',
    'adjusted_at', 'adjusted_by', 'adjust_reason',
])]
class BorrowBlock extends Model
{
    protected function casts(): array
    {
        return [
            'blocked_until' => 'immutable_datetime',
            'lifted_at' => 'immutable_datetime',
            'adjusted_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function lifter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function adjuster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    /** @param  Builder<BorrowBlock>  $query */
    public function scopeForSubject(Builder $query, BorrowSubject $subject): void
    {
        $subject->scope($query);
    }

    /**
     * 尚未解封、也還沒到期的封鎖。
     *
     * @param  Builder<BorrowBlock>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('lifted_at')->where('blocked_until', '>', now());
    }

    public static function activeFor(BorrowSubject $subject): ?self
    {
        return static::query()
            ->active()
            ->forSubject($subject)
            ->orderByDesc('blocked_until')
            ->first();
    }
}
