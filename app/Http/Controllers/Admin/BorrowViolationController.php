<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BorrowViolationKind;
use App\Http\Controllers\Controller;
use App\Models\BorrowBlock;
use App\Models\BorrowViolation;
use App\Support\Toast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 違規紀錄與封鎖名單。封鎖由系統依設定自動建立，這裡只負責看與解封。
 */
class BorrowViolationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'kind' => ['nullable', Rule::enum(BorrowViolationKind::class)],
            'q' => ['nullable', 'string', 'max:50'],
        ]);

        $violations = BorrowViolation::query()
            ->when($filters['kind'] ?? null, fn (Builder $q, string $kind) => $q->where('kind', $kind))
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $w) => $w
                ->where('borrower_name', 'ilike', "%{$term}%")
                ->orWhere('student_id', 'ilike', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")))
            ->orderByDesc('occurred_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (BorrowViolation $v) => [
                'id' => $v->id,
                'kind' => $v->kind->value,
                'label' => $v->kind->label(),
                'borrower_name' => $v->borrower_name,
                'student_id' => $v->student_id,
                'phone' => $v->phone,
                'borrow_request_id' => $v->borrow_request_id,
                'occurred_at' => $v->occurred_at->format('Y-m-d H:i'),
                'note' => $v->note,
            ]);

        return Inertia::render('Admin/Violations/Index', [
            'filters' => $filters,
            'violations' => $violations,
            'kinds' => BorrowViolationKind::options(),
            'blocks' => BorrowBlock::query()
                ->active()
                ->orderBy('blocked_until')
                ->get()
                ->map(fn (BorrowBlock $b) => [
                    'id' => $b->id,
                    'borrower_name' => $b->borrower_name,
                    'student_id' => $b->student_id,
                    'phone' => $b->phone,
                    'reason' => $b->reason,
                    'blocked_until' => $b->blocked_until->format('Y-m-d H:i'),
                    'until_date' => $b->blocked_until->toDateString(),
                    'automatic' => $b->created_by === null,
                    'adjusted' => $b->adjusted_at !== null,
                    'adjust_reason' => $b->adjust_reason,
                ]),
        ]);
    }

    /** 調整封鎖期限。送出的是日期，實際封鎖到那一天結束。 */
    public function adjust(Request $request, BorrowBlock $block): RedirectResponse
    {
        $data = $request->validate([
            'until' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        if ($block->lifted_at !== null) {
            Toast::error('這筆封鎖已經解除了，不能再調整期限。');

            return back();
        }

        $until = CarbonImmutable::parse($data['until'])->endOfDay();

        $block->update([
            'blocked_until' => $until,
            'adjusted_at' => now(),
            'adjusted_by' => $request->user()->id,
            'adjust_reason' => $data['reason'],
        ]);

        Toast::success("已將 {$block->borrower_name} 的封鎖期限改為 {$until->format('Y-m-d')}。");

        return back();
    }

    /** 提前解除封鎖。解封後的違規重新計次，所以不會一取消就立刻被封回去。 */
    public function lift(Request $request, BorrowBlock $block): RedirectResponse
    {
        $reason = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
        ])['reason'];

        if ($block->lifted_at !== null) {
            Toast::error('這筆封鎖已經解除過了。');

            return back();
        }

        $block->update([
            'lifted_at' => now(),
            'lifted_by' => $request->user()->id,
            'lift_reason' => $reason,
        ]);

        Toast::success("已解除 {$block->borrower_name} 的借用封鎖。");

        return back();
    }
}
