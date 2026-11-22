<?php

namespace App\Support;

use App\Models\BorrowRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 違規紀錄與封鎖的對象。
 *
 * 訪客借用沒有帳號，所以身分用「學號或手機」兩個 key 一起比對：
 * 任一相符就算同一個人，換手機但用同一個學號（或反過來）都躲不掉。
 */
final readonly class BorrowSubject
{
    public function __construct(
        public ?string $studentId,
        public string $phone,
        public string $name,
    ) {}

    public static function for(?User $user, string $phone, string $name): self
    {
        return new self($user?->student_id, $phone, $name);
    }

    public static function ofRequest(BorrowRequest $request): self
    {
        return new self(
            $request->borrower?->student_id,
            $request->phone,
            $request->borrower_name,
        );
    }

    /**
     * 套用「這個人」的查詢條件。學號為空時只比手機。
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public function scope(Builder $query): void
    {
        $studentId = $this->studentId;
        $phone = $this->phone;

        $query->where(function (Builder $q) use ($studentId, $phone) {
            $q->where('phone', $phone);

            if ($studentId !== null && $studentId !== '') {
                $q->orWhere('student_id', $studentId);
            }
        });
    }

    /** @return array{student_id: string|null, phone: string, borrower_name: string} */
    public function attributes(): array
    {
        return [
            'student_id' => $this->studentId,
            'phone' => $this->phone,
            'borrower_name' => $this->name,
        ];
    }
}
