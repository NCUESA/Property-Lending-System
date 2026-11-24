<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Borrow\CancelBorrowRequest;
use App\Actions\Borrow\ExtendBorrowRequest;
use App\Actions\Borrow\LendOutItems;
use App\Actions\Borrow\ReturnItems;
use App\Enums\BorrowRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExtendBorrowRequestRequest;
use App\Http\Requests\Admin\LendBorrowRequestRequest;
use App\Http\Requests\Admin\ReturnBorrowRequestRequest;
use App\Http\Requests\Admin\UpdateBorrowRequestRequest;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestOverview;
use App\Models\Location;
use App\Models\Setting;
use App\Support\Toast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 值班人員的借用單管理：列表、明細、出借、歸還、退回、修改備註。
 */
class BorrowRequestController extends Controller
{
    /** 每頁筆數的可選值，第一個是預設值，與器材列表相同。 */
    public const PER_PAGE_OPTIONS = PropertyController::PER_PAGE_OPTIONS;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(BorrowRequestStatus::class)],
            'location_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
        ]);

        $borrows = BorrowRequestOverview::query()
            ->when($filters['status'] ?? null, fn (Builder $q, array $status) => $q->whereIn('status', $status))
            ->when($filters['location_id'] ?? null, fn (Builder $q, int $id) => $q->where('location_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $q, string $d) => $q->whereDate('borrow_date', '>=', $d))
            ->when($filters['to'] ?? null, fn (Builder $q, string $d) => $q->whereDate('borrow_date', '<=', $d))
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where(fn (Builder $w) => $w
                ->where('borrower_name', 'ilike', "%{$term}%")
                ->orWhere('borrower_department', 'ilike', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhereHas('items.property', fn (Builder $p) => $p->where('ssid', 'ilike', "%{$term}%"))
                ->when(ctype_digit($term), fn (Builder $w) => $w->orWhere('id', (int) $term))))
            ->with('location:id,name')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? self::PER_PAGE_OPTIONS[0])
            ->withQueryString();

        return Inertia::render('Admin/Borrows/Index', [
            'filters' => $filters,
            'borrows' => $borrows,
            'statuses' => collect(BorrowRequestStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'locations' => Location::pickupPoints()->get(['id', 'name']),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function show(BorrowRequest $borrow): Response
    {
        $overview = BorrowRequestOverview::query()
            ->with([
                'location:id,name',
                'items.property:id,ssid,name,second_name,location_id',
                'items.property.location:id,name',
                'items.pool:id,name',
                'items.returner:id,name',
                'extender:id,name',
                'lender:id,name',
                'returner:id,name',
                'borrower:id,name,student_id',
            ])
            ->findOrFail($borrow->id);

        return Inertia::render('Admin/Borrows/Show', [
            'borrow' => $overview,
            'defaultDepositAmount' => (int) Setting::get('borrow.deposit_amount', 100),
            'depositBoxCount' => (int) Setting::get('borrow.deposit_box_count', 20),
            // 可自訂天數時借用人可自選歸還日，超過規定要讓值班人員看得出來
            'maxDays' => max(1, (int) Setting::get('borrow.max_days', 7)),
        ]);
    }

    public function update(UpdateBorrowRequestRequest $request, BorrowRequest $borrow): RedirectResponse
    {
        $borrow->update($request->validated());

        Toast::success('已更新借用單。');

        return back();
    }

    public function lend(LendBorrowRequestRequest $request, BorrowRequest $borrow, LendOutItems $lend): RedirectResponse
    {
        $lend->handle($borrow, $request->user(), $request->validated());

        Toast::success('已完成出借。');

        return back();
    }

    public function return(ReturnBorrowRequestRequest $request, BorrowRequest $borrow, ReturnItems $return): RedirectResponse
    {
        $return->handle($borrow, $request->user(), $request->validated());

        Toast::success('已登記歸還。');

        return back();
    }

    public function extend(ExtendBorrowRequestRequest $request, BorrowRequest $borrow, ExtendBorrowRequest $extend): RedirectResponse
    {
        $data = $request->validated();

        $extend->handle($borrow, $request->user(), $data['due_date'], $data['reason']);

        Toast::success('已展延歸還日。');

        return back();
    }

    public function cancel(Request $request, BorrowRequest $borrow, CancelBorrowRequest $cancel): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $cancel->handle($borrow, $request->user(), $data['reason'] ?? null);

        Toast::success('已退回借用單。');

        return back();
    }
}
