<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BorrowItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PropertyPoolRequest;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyPool;
use App\Support\Toast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PropertyPoolController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Pools/Index', [
            'pools' => PropertyPool::query()
                ->withAvailableQuantity()
                ->withCount(['properties', 'properties as lendable_count' => fn (Builder $q) => $q->where('is_lendable', true)])
                ->with('location:id,name')
                ->orderBy('location_id')->orderBy('name')
                ->get(),
            'locations' => Location::pickupPoints()->get(['id', 'name']),
        ]);
    }

    public function store(PropertyPoolRequest $request): RedirectResponse
    {
        PropertyPool::create($request->validated());

        Toast::success('已新增品項。');

        return back();
    }

    public function update(PropertyPoolRequest $request, PropertyPool $pool): RedirectResponse
    {
        $pool->update($request->validated());

        Toast::success('已更新品項。');

        return back();
    }

    /**
     * 可挑進此品項的器材清單。範圍是該品項取用地點所服務的存放地點
     * （例如「進德」可以借出 307 倉庫的器材），已屬於其他品項的也一併回傳並標示，
     * 否則管理者會看不懂某件器材為什麼不在清單裡。
     */
    public function properties(PropertyPool $pool): JsonResponse
    {
        $properties = Property::query()
            ->whereIn('location_id', $pool->location->servedLocationIds())
            ->with(['location:id,name', 'pool:id,name'])
            ->orderBy('ssid')
            ->get(['id', 'ssid', 'name', 'second_name', 'location_id', 'property_pool_id', 'is_lendable']);

        return response()->json(['properties' => $properties]);
    }

    /**
     * 整池一次設定成員：不在清單裡的移出（property_pool_id 變回 null），清單裡的移入。
     */
    public function syncProperties(Request $request, PropertyPool $pool): RedirectResponse
    {
        $ids = $request->validate([
            'property_ids' => ['present', 'array'],
            'property_ids.*' => ['integer', 'distinct', 'exists:properties,id'],
        ])['property_ids'];

        $selected = Property::query()->whereIn('id', $ids)->get(['id', 'ssid', 'location_id', 'property_pool_id', 'is_lendable']);

        $outside = $selected
            ->whereNotIn('location_id', $pool->location->servedLocationIds())
            ->pluck('ssid');

        if ($outside->isNotEmpty()) {
            throw ValidationException::withMessages([
                'property_ids' => "下列器材不在「{$pool->location->name}」的出借範圍內：".$outside->join('、'),
            ]);
        }

        $taken = $selected
            ->filter(fn (Property $p) => $p->property_pool_id !== null && $p->property_pool_id !== $pool->id)
            ->pluck('ssid');

        if ($taken->isNotEmpty()) {
            throw ValidationException::withMessages([
                'property_ids' => '下列器材已屬於其他品項，請先從原品項移出：'.$taken->join('、'),
            ]);
        }

        // 池內可借件數不能少於進行中（未歸還）的數量，否則借用紀錄會對不上實體器材。
        // 資料庫的 trigger 只在寫入 borrow_request_items 時檢查，縮減池子不會觸發，所以這裡要自己擋。
        $inUse = (int) $pool->borrowItems()
            ->whereIn('status', array_map(fn (BorrowItemStatus $s) => $s->value, BorrowItemStatus::ACTIVE))
            ->sum(DB::raw('quantity - returned_quantity'));

        $lendable = $selected->where('is_lendable', true)->count();

        if ($lendable < $inUse) {
            throw ValidationException::withMessages([
                'property_ids' => "此品項目前有 {$inUse} 件借出中未歸還，池內可借器材不能少於這個數量（目前選了 {$lendable} 件）。",
            ]);
        }

        DB::transaction(function () use ($pool, $ids) {
            Property::query()
                ->where('property_pool_id', $pool->id)
                ->whereNotIn('id', $ids)
                ->update(['property_pool_id' => null]);

            Property::query()
                ->whereIn('id', $ids)
                ->update(['property_pool_id' => $pool->id]);
        });

        Toast::success('已更新池內器材。');

        return back();
    }

    public function destroy(PropertyPool $pool): RedirectResponse
    {
        if ($pool->borrowItems()->exists()) {
            throw ValidationException::withMessages(['pool' => '此品項已有借用紀錄，不能刪除。']);
        }

        $pool->delete(); // 池內器材的 property_pool_id 會變成 null，回到逐件借用

        Toast::success('已刪除品項，池內器材改為逐件借用。');

        return back();
    }
}
