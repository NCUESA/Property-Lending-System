<?php

namespace App\Http\Controllers;

use App\Actions\Borrow\CancelBorrowRequest;
use App\Enums\BorrowRequestStatus;
use App\Models\BorrowBlock;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestItem;
use App\Models\BorrowRequestOverview;
use App\Models\BorrowViolation;
use App\Support\BorrowSubject;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 借用人自己的紀錄：看進度、看歷史、取消還沒取件的申請。
 */
class MyBorrowController extends Controller
{
    /** 還沒結束的單，置頂顯示。 */
    private const ACTIVE = [
        BorrowRequestStatus::Pending,
        BorrowRequestStatus::Lent,
        BorrowRequestStatus::PartiallyReturned,
        BorrowRequestStatus::Overdue,
    ];

    private const RELATIONS = [
        'location:id,name',
        'items.property:id,ssid,name',
        'items.pool:id,name',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();

        $history = BorrowRequestOverview::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', array_map(fn (BorrowRequestStatus $s) => $s->value, self::ACTIVE))
            ->with(self::RELATIONS)
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through($this->present(...));

        $active = BorrowRequestOverview::query()
            ->where('user_id', $user->id)
            ->whereStatus(self::ACTIVE)
            ->with(self::RELATIONS)
            ->orderBy('due_date')
            ->get()
            ->map($this->present(...));

        /*
         * 封鎖與違規是跟著「學號或手機」走的，所以要把這個人用過的手機都算進來，
         * 否則他換個號碼送申請就查不到自己其實被封鎖了。
         */
        $subjects = $this->subjectsOf($user->id, $user->student_id);

        return Inertia::render('Borrow/MyBorrows', [
            'active' => $active,
            'history' => $history,
            'block' => $this->activeBlock($subjects),
            'violations' => $this->violationsOf($subjects),
        ]);
    }

    public function cancel(Request $request, BorrowRequest $borrow, CancelBorrowRequest $cancel): RedirectResponse
    {
        $reason = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ])['reason'] ?? null;

        $cancel->byBorrower($borrow, $request->user(), $reason);

        Toast::success("已取消借用申請 #{$borrow->id}。");

        return back();
    }

    /**
     * 這個人用過的身分組合。學號固定，手機可能每次填不一樣。
     *
     * @return list<BorrowSubject>
     */
    private function subjectsOf(int $userId, ?string $studentId): array
    {
        $phones = BorrowRequest::query()
            ->where('user_id', $userId)
            ->distinct()
            ->pluck('phone')
            ->all();

        if ($phones === []) {
            $phones = ['']; // 沒借過也要能查到用學號開的封鎖
        }

        return array_values(
            array_map(fn (string $phone) => new BorrowSubject($studentId, $phone, ''), $phones)
        );
    }

    /**
     * @param  list<BorrowSubject>  $subjects
     * @return array{reason: string, blocked_until: string}|null
     */
    private function activeBlock(array $subjects): ?array
    {
        foreach ($subjects as $subject) {
            if ($block = BorrowBlock::activeFor($subject)) {
                return [
                    'reason' => $block->reason,
                    'blocked_until' => $block->blocked_until->format('Y-m-d H:i'),
                ];
            }
        }

        return null;
    }

    /**
     * @param  list<BorrowSubject>  $subjects
     * @return list<array{kind: string, label: string, occurred_at: string, borrow_request_id: int|null, note: string|null}>
     */
    private function violationsOf(array $subjects): array
    {
        $rows = BorrowViolation::query()
            ->where(function ($query) use ($subjects) {
                foreach ($subjects as $subject) {
                    $query->orWhere(fn ($q) => $subject->scope($q));
                }
            })
            ->orderByDesc('occurred_at')
            ->limit(20)
            ->get()
            ->map(fn (BorrowViolation $v) => [
                'kind' => $v->kind->value,
                'label' => $v->kind->label(),
                'occurred_at' => $v->occurred_at->format('Y-m-d H:i'),
                'borrow_request_id' => $v->borrow_request_id,
                'note' => $v->note,
            ])
            ->all();

        return array_values($rows);
    }

    /**
     * 只回傳借用人自己需要的欄位：押金箱號、證件狀態這些是櫃檯用的，不外流。
     *
     * @return array<string, mixed>
     */
    private function present(BorrowRequestOverview $borrow): array
    {
        return [
            'id' => $borrow->id,
            'status' => $borrow->status->value,
            'location' => $borrow->location->name,
            'borrow_date' => $borrow->borrow_date->toDateString(),
            'due_date' => $borrow->due_date->toDateString(),
            'returned_at' => $borrow->returned_at?->format('Y-m-d H:i'),
            'item_count' => $borrow->item_count,
            'cancellable' => $borrow->status === BorrowRequestStatus::Pending,
            'items' => $borrow->items
                // 明細是「指定器材」或「大量借用品項」擇一
                ->map(fn (BorrowRequestItem $item) => [
                    'id' => $item->id,
                    'name' => $item->displayName(),
                    'ssid' => $item->displaySsid(),
                    'quantity' => $item->quantity,
                    'status' => $item->status->value,
                ])
                ->all(),
        ];
    }
}
