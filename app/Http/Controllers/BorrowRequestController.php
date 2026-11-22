<?php

namespace App\Http\Controllers;

use App\Actions\Borrow\SubmitBorrowRequest;
use App\Http\Requests\Borrow\StoreBorrowRequestRequest;
use App\Models\BorrowPeriod;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyPool;
use App\Models\Setting;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 公開的借用申請（訪客可用；登入後可借有權限的器材）。
 */
class BorrowRequestController extends Controller
{
    /**
     * 開放預借時，日曆最多讓使用者往後翻幾天。
     * 系統沒有「可預借多久」這個設定，但日曆必須有個邊界才畫得出來，所以在這裡定一個。
     */
    public const CALENDAR_HORIZON_DAYS = 180;

    public function create(Request $request): Response
    {
        $user = $request->user();
        $locations = Location::pickupPoints()->get(['id', 'code', 'name']);
        $location = $locations->firstWhere('code', $request->query('location'));

        $maxDays = max(1, (int) Setting::get('borrow.max_days', 7));
        $allowAdvance = (bool) Setting::get('borrow.allow_advance', false);
        // 不開放預借時借用日只能是今天，日曆不必知道更後面的休館日
        $lastBorrowDate = today()->addDays($allowAdvance ? self::CALENDAR_HORIZON_DAYS : 0);
        // 歸還日還能再往後走：照規定是 maxDays − 1 天，關閉彈性借用時則放寬到整個 horizon
        $flexible = (bool) Setting::get('borrow.flexible_duration', true);
        $lastDate = $lastBorrowDate->copy()
            ->addDays($flexible ? $maxDays - 1 : self::CALENDAR_HORIZON_DAYS);

        return Inertia::render('Borrow/Create', [
            'isOpen' => BorrowPeriod::isOpenOn(today()),
            'rules' => [
                'maxDays' => $maxDays,
                'depositAmount' => (int) Setting::get('borrow.deposit_amount', 100),
                'allowAdvance' => $allowAdvance,
                'flexibleDuration' => (bool) Setting::get('borrow.flexible_duration', true),
                'notices' => Setting::get('borrow.notices', []),
            ],
            'calendar' => [
                'today' => today()->toDateString(),
                'lastBorrowDate' => $lastBorrowDate->toDateString(),
                'lastDate' => $lastDate->toDateString(),
                // 日曆把這些日子畫成不可選，和 SubmitBorrowRequest 的檢查同一套規則
                'closedDates' => BorrowPeriod::closedDatesBetween(today(), $lastDate),
            ],
            'locations' => $locations,
            'selectedLocationId' => $location?->id,
            'properties' => fn () => $location === null ? [] : Property::query()
                ->serial()
                ->pickupableFrom($location)
                ->available()
                ->borrowableBy($user)
                ->with(['subcategory.category:id,code,name', 'location:id,name'])
                ->orderBy('ssid')
                ->get()
                ->map(fn (Property $p) => [
                    'id' => $p->id,
                    'ssid' => $p->ssid,
                    'name' => $p->name,
                    'second_name' => $p->second_name,
                    'spec' => $p->spec,
                    'category' => $p->subcategory->category->name,
                    'subcategory' => $p->subcategory->name,
                    'location' => $p->location->name,
                    'image_url' => $p->image_path ? asset('storage/'.$p->image_path) : null,
                    'restricted' => $p->auth_level > 0,
                ]),
            'pools' => fn () => $location === null ? [] : PropertyPool::query()
                ->where('location_id', $location->id)
                ->borrowableBy($user)
                ->withAvailableQuantity()
                ->orderBy('name')
                ->get()
                ->map(fn (PropertyPool $pool) => [
                    'id' => $pool->id,
                    'name' => $pool->name,
                    'available' => max(0, (int) $pool->available_quantity),
                    'image_url' => $pool->image_path ? asset('storage/'.$pool->image_path) : null,
                    'restricted' => $pool->auth_level > 0,
                ]),
            'prefill' => $user ? ['borrower_name' => $user->name, 'email' => $user->email] : null,
        ]);
    }

    public function store(StoreBorrowRequestRequest $request, SubmitBorrowRequest $submit): RedirectResponse
    {
        $borrow = $submit->handle($request->validated(), $request->user());

        Toast::success("已送出借用申請（單號 #{$borrow->id}），請到現場辦理借用。");

        return to_route('borrow.create', ['location' => $borrow->location->code]);
    }
}
