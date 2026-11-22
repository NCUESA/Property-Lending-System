<?php

namespace App\Actions\Borrow;

use App\Enums\BorrowViolationKind;
use App\Models\BorrowBlock;
use App\Models\BorrowRequest;
use App\Models\BorrowViolation;
use App\Support\BorrowSubject;
use Illuminate\Support\Facades\DB;

/**
 * 記一筆違規，必要時自動封鎖。
 *
 * 同一張單的同一種違規只會留下一筆（資料庫的 partial unique index 擋著），
 * 所以排程重跑、或使用者連點兩下取消，都不會灌出重複紀錄或重複計次。
 */
class RecordBorrowViolation
{
    public function handle(
        BorrowViolationKind $kind,
        BorrowRequest $request,
        ?string $note = null,
    ): BorrowViolation {
        $subject = BorrowSubject::ofRequest($request);

        return DB::transaction(function () use ($kind, $request, $subject, $note) {
            /*
             * 「逾期歸還」取代同一張單的「逾期未還」：東西還回來之後，
             * 同一次遲還只該留下一筆，不然每天掃到的那筆會和它重複計次。
             */
            if ($superseded = $kind->supersedes()) {
                BorrowViolation::query()
                    ->where('kind', $superseded)
                    ->where('borrow_request_id', $request->id)
                    ->delete();
            }

            $violation = BorrowViolation::firstOrCreate(
                ['kind' => $kind, 'borrow_request_id' => $request->id],
                [
                    ...$subject->attributes(),
                    'user_id' => $request->user_id,
                    'occurred_at' => now(),
                    'note' => $note,
                ],
            );

            // 只有新記下來的那一筆才計次，否則重跑排程會把人一路封鎖下去
            if ($violation->wasRecentlyCreated) {
                $this->blockIfOverLimit($kind, $subject);
            }

            return $violation;
        });
    }

    private function blockIfOverLimit(BorrowViolationKind $kind, BorrowSubject $subject): void
    {
        $limit = $kind->limit();

        // 0 代表這一種只記錄、不封鎖
        if ($limit < 1 || BorrowBlock::activeFor($subject) !== null) {
            return;
        }

        /*
         * 計次只看「上次解封之後」發生的違規：
         * 管理員解封就是給一次機會，不能一違規就因為舊紀錄立刻又被封回去。
         */
        $since = BorrowBlock::query()
            ->forSubject($subject)
            ->whereNotNull('lifted_at')
            ->max('lifted_at');

        $count = BorrowViolation::query()
            ->forSubject($subject)
            ->where('kind', $kind)
            ->whereDate('occurred_at', today())
            // 用 >= 而不是 >：timestamp 欄位只存到秒，解封與違規同一秒發生時 > 會把它漏掉
            ->when($since, fn ($q) => $q->where('occurred_at', '>=', $since))
            ->count();

        if ($count < $limit) {
            return;
        }

        BorrowBlock::create([
            ...$subject->attributes(),
            'reason' => "當日{$kind->label()} {$count} 次，達上限 {$limit} 次",
            // 封鎖到第 N 天結束，1 就是「今天剩下的時間」
            'blocked_until' => today()->addDays($kind->blockDays() - 1)->endOfDay(),
            'created_by' => null, // 系統自動
        ]);
    }
}
