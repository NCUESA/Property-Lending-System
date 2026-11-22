<?php

use App\Enums\BorrowItemStatus;
use App\Enums\BorrowOpenMode;
use App\Enums\BorrowRequestStatus;
use App\Models\BorrowPeriod;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestItem;
use App\Models\BorrowRequestOverview;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyPool;
use App\Models\PropertySubcategory;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserGroupGrant;
use App\Support\PropertyCode;
use Database\Seeders\LocationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(LocationSeeder::class);
});

function loc(string $code): Location
{
    return Location::where('code', $code)->firstOrFail();
}

function idsOf($query): array
{
    return $query->pluck('id')->sort()->values()->all();
}

function sorted(array $ids): array
{
    sort($ids);

    return $ids;
}

describe('指定器材的借用', function () {
    it('一件器材只能有一筆進行中的借用', function (BorrowItemStatus $existing) {
        $property = Property::factory()->create(['location_id' => loc('jinde')->id]);
        BorrowRequestItem::factory()->create(['property_id' => $property->id, 'status' => $existing]);

        BorrowRequestItem::factory()->create(['property_id' => $property->id]);
    })->with([BorrowItemStatus::Pending, BorrowItemStatus::Lent])
        ->throws(UniqueConstraintViolationException::class);

    it('歸還或退回後可以再借', function (BorrowItemStatus $finished) {
        $property = Property::factory()->create(['location_id' => loc('jinde')->id]);
        BorrowRequestItem::factory()->create(['property_id' => $property->id, 'status' => $finished]);

        BorrowRequestItem::factory()->create(['property_id' => $property->id]);

        expect($property->borrowItems()->count())->toBe(2);
    })->with([BorrowItemStatus::Returned, BorrowItemStatus::Cancelled]);

    it('資料庫拒絕不合法的狀態字串', function () {
        $item = BorrowRequestItem::factory()->create();

        DB::table('borrow_request_items')->where('id', $item->id)->update(['status' => '2']);
    })->throws(QueryException::class);

    it('明細必須是「指定器材」或「品項池＋數量」其中一種', function (array $attrs) {
        BorrowRequestItem::factory()->create($attrs);
    })->with([
        '兩者都有' => fn () => ['property_pool_id' => PropertyPool::factory()->create()->id],
        '指定器材數量不是 1' => fn () => ['quantity' => 2],
        '兩者都沒有' => fn () => ['property_id' => null],
    ])->throws(QueryException::class);

    it('available 排除不開放借用與進行中的器材', function () {
        $free = Property::factory()->create();
        Property::factory()->notLendable()->create();
        $lent = Property::factory()->create();
        BorrowRequestItem::factory()->create(['property_id' => $lent->id, 'status' => BorrowItemStatus::Lent]);
        $returned = Property::factory()->create();
        BorrowRequestItem::factory()->create(['property_id' => $returned->id, 'status' => BorrowItemStatus::Returned]);

        expect(idsOf(Property::available()))->toBe(sorted([$free->id, $returned->id]));
    });

    it('有借用紀錄的器材不能被刪除', function () {
        $item = BorrowRequestItem::factory()->create(['status' => BorrowItemStatus::Returned]);

        $item->property->delete();
    })->throws(QueryException::class);
});

describe('bulk 品項池', function () {
    beforeEach(function () {
        $this->pool = PropertyPool::factory()->create(['location_id' => loc('jinde')->id]);
        Property::factory()->count(10)->inPool($this->pool)->create();
        Property::factory()->notLendable()->inPool($this->pool)->create(); // 壞掉的不算
    });

    it('可借數量 = 可借件數 − 進行中未歸還數量', function () {
        BorrowRequestItem::factory()->fromPool($this->pool, 4)->create(['status' => BorrowItemStatus::Lent, 'returned_quantity' => 1]);
        BorrowRequestItem::factory()->fromPool($this->pool, 2)->create();
        BorrowRequestItem::factory()->fromPool($this->pool, 5)->create(['status' => BorrowItemStatus::Returned, 'returned_quantity' => 5]);

        expect(PropertyPool::withAvailableQuantity()->find($this->pool->id)->available_quantity)->toBe(10 - 3 - 2);
    });

    it('剛好借完可以，超過一件就被資料庫擋下', function () {
        BorrowRequestItem::factory()->fromPool($this->pool, 7)->create();
        BorrowRequestItem::factory()->fromPool($this->pool, 3)->create();

        expect(fn () => BorrowRequestItem::factory()->fromPool($this->pool, 1)->create())
            ->toThrow(QueryException::class, '數量不足');
    });

    it('改大數量時也會檢查', function () {
        $item = BorrowRequestItem::factory()->fromPool($this->pool, 5)->create();
        BorrowRequestItem::factory()->fromPool($this->pool, 5)->create();

        $item->update(['quantity' => 6]);
    })->throws(QueryException::class, '數量不足');

    it('部分歸還後釋出的數量可以再借', function () {
        $item = BorrowRequestItem::factory()->fromPool($this->pool, 10)->create(['status' => BorrowItemStatus::Lent]);
        $item->update(['returned_quantity' => 4]);

        BorrowRequestItem::factory()->fromPool($this->pool, 4)->create();

        expect(PropertyPool::withAvailableQuantity()->find($this->pool->id)->available_quantity)->toBe(0);
    });

    it('歸還數量不能超過借用數量', function () {
        BorrowRequestItem::factory()->fromPool($this->pool, 2)->create(['returned_quantity' => 3]);
    })->throws(QueryException::class);
});

describe('取用地點', function () {
    it('進德可借進德與 307，寶山只借寶山，405 不開放', function () {
        $ids = [];
        foreach (['jinde', 'baosan', '307', '405'] as $code) {
            $ids[$code] = Property::factory()->create(['location_id' => loc($code)->id])->id;
        }

        expect(idsOf(Property::pickupableFrom(loc('jinde'))))->toBe(sorted([$ids['jinde'], $ids['307']]))
            ->and(idsOf(Property::pickupableFrom(loc('baosan'))))->toBe([$ids['baosan']])
            ->and(Location::pickupPoints()->pluck('code')->all())->toBe(['jinde', 'baosan']);
    });
});

describe('借用權限與群組', function () {
    beforeEach(function () {
        $this->category = PropertyCategory::factory()->create(['code' => '200']);
        $this->sub = PropertySubcategory::factory()->create(['property_category_id' => $this->category->id, 'code' => '0']);
        $this->otherSub = PropertySubcategory::factory()->create(['property_category_id' => $this->category->id, 'code' => '1']);

        $this->public = Property::factory()->create(['property_subcategory_id' => $this->sub->id]);
        $this->restricted = Property::factory()->restricted()->create(['property_subcategory_id' => $this->sub->id]);
        $this->restrictedOther = Property::factory()->restricted(2)->create(['property_subcategory_id' => $this->otherSub->id]);

        $this->user = User::factory()->create();
        $this->group = UserGroup::factory()->create();
        $this->group->members()->attach($this->user);
    });

    it('訪客只能借 auth_level 0', function () {
        expect(idsOf(Property::borrowableBy(null)))->toBe([$this->public->id]);
    });

    it('登入但沒有授權，等同訪客', function () {
        expect(idsOf(Property::borrowableBy($this->user)))->toBe([$this->public->id]);
    });

    it('依授權範圍開放', function (string $grant, Closure $expected) {
        $target = match ($grant) {
            'property_id' => $this->restricted->id,
            'property_subcategory_id' => $this->sub->id,
            'property_category_id' => $this->category->id,
        };
        $this->group->grants()->create([$grant => $target]);

        expect(idsOf(Property::borrowableBy($this->user)))->toBe(sorted($expected->call($this)));
    })->with([
        '單一器材' => ['property_id', fn () => [$this->public->id, $this->restricted->id]],
        '次要類別' => ['property_subcategory_id', fn () => [$this->public->id, $this->restricted->id]],
        '主要類別' => ['property_category_id', fn () => [$this->public->id, $this->restricted->id, $this->restrictedOther->id]],
    ]);

    it('子群組成員繼承所有上層群組的授權（多層）', function () {
        $top = UserGroup::factory()->create();
        $middle = UserGroup::factory()->create();
        $top->subgroups()->attach($middle);
        $middle->subgroups()->attach($this->group);
        $top->grants()->create(['property_id' => $this->restrictedOther->id]);

        expect(idsOf(Property::borrowableBy($this->user)))->toBe(sorted([$this->public->id, $this->restrictedOther->id]))
            ->and($this->user->effectiveGroups()->pluck('user_groups.id')->sort()->values()->all())
            ->toBe(sorted([$top->id, $middle->id, $this->group->id]))
            ->and($top->effectiveMembers()->pluck('users.id')->all())->toBe([$this->user->id]);
    });

    it('上層群組的成員不會得到子群組的授權', function () {
        $parent = UserGroup::factory()->create();
        $parent->subgroups()->attach($this->group);
        $outsider = User::factory()->create();
        $parent->members()->attach($outsider);
        $this->group->grants()->create(['property_id' => $this->restricted->id]);

        expect(idsOf(Property::borrowableBy($outsider)))->toBe([$this->public->id]);
    });

    it('群組不能互相包含形成循環', function () {
        $a = UserGroup::factory()->create();
        $b = UserGroup::factory()->create();
        $a->subgroups()->attach($b);
        $b->subgroups()->attach($this->group);

        $this->group->subgroups()->attach($a);
    })->throws(QueryException::class, '循環');

    it('群組不能包含自己', function () {
        $this->group->subgroups()->attach($this->group);
    })->throws(QueryException::class);

    it('一筆授權只能指定一種對象', function () {
        UserGroupGrant::create([
            'user_group_id' => $this->group->id,
            'property_id' => $this->restricted->id,
            'property_subcategory_id' => $this->sub->id,
        ]);
    })->throws(QueryException::class);

    it('同一群組不能重複授權同一對象', function () {
        $this->group->grants()->create(['property_id' => $this->restricted->id]);
        $this->group->grants()->create(['property_id' => $this->restricted->id]);
    })->throws(UniqueConstraintViolationException::class);

    it('品項池的權限看品項池本身', function () {
        $open = PropertyPool::factory()->create();
        $locked = PropertyPool::factory()->create(['auth_level' => 1]);

        expect(idsOf(PropertyPool::borrowableBy($this->user)))->toBe([$open->id]);

        $this->group->grants()->create(['property_pool_id' => $locked->id]);

        expect(idsOf(PropertyPool::borrowableBy($this->user)))->toBe(sorted([$open->id, $locked->id]));
    });
});

describe('借用單狀態（VIEW 即時計算）', function () {
    function requestWith(array $itemStatuses, array $attrs = []): BorrowRequest
    {
        $request = BorrowRequest::factory()->create($attrs);
        foreach ($itemStatuses as $status) {
            $extra = is_array($status) ? $status : ['status' => $status];
            BorrowRequestItem::factory()->create(['borrow_request_id' => $request->id, ...$extra]);
        }

        return $request;
    }

    it('依明細與日期算出狀態', function (array $items, array $dates, BorrowRequestStatus $expected) {
        $request = requestWith($items, $dates);

        expect($request->overview->status)->toBe($expected);
    })->with([
        '今天申請' => [[BorrowItemStatus::Pending], [], BorrowRequestStatus::Pending],
        '借用日已過未取件' => [[BorrowItemStatus::Pending], ['borrow_date' => today()->subDays(2), 'due_date' => today()], BorrowRequestStatus::Expired],
        '借出中' => [[BorrowItemStatus::Lent, BorrowItemStatus::Lent], [], BorrowRequestStatus::Lent],
        '部分歸還' => [[BorrowItemStatus::Lent, BorrowItemStatus::Returned], [], BorrowRequestStatus::PartiallyReturned],
        '過了歸還日' => [[BorrowItemStatus::Lent], ['borrow_date' => today()->subDays(5), 'due_date' => today()->subDay()], BorrowRequestStatus::Overdue],
        '歸還日當天還不算逾期' => [[BorrowItemStatus::Lent], ['borrow_date' => today()->subDays(5), 'due_date' => today()], BorrowRequestStatus::Lent],
        '全部歸還' => [[BorrowItemStatus::Returned, BorrowItemStatus::Cancelled], [], BorrowRequestStatus::Returned],
        '全部退回' => [[BorrowItemStatus::Cancelled, BorrowItemStatus::Cancelled], [], BorrowRequestStatus::Cancelled],
        '結案日超過歸還日是逾期歸還' => [[BorrowItemStatus::Returned], ['borrow_date' => today()->subDays(9), 'due_date' => today()->subDays(3), 'returned_at' => today()->subDay()], BorrowRequestStatus::ReturnedLate],
        '歸還日當天結案不算逾期' => [[BorrowItemStatus::Returned], ['borrow_date' => today()->subDays(9), 'due_date' => today()->subDays(3), 'returned_at' => today()->subDays(3)->setTime(23, 30)], BorrowRequestStatus::Returned],
        // 舊資料沒有 returned_at，無從判斷是否遲還，維持已歸還
        '沒有結案時間就還是已歸還' => [[BorrowItemStatus::Returned], ['borrow_date' => today()->subDays(9), 'due_date' => today()->subDays(3)], BorrowRequestStatus::Returned],
    ]);

    it('bulk 部分歸還也算部分歸還', function () {
        $pool = PropertyPool::factory()->create();
        Property::factory()->count(5)->inPool($pool)->create();
        $request = BorrowRequest::factory()->create();
        BorrowRequestItem::factory()->fromPool($pool, 5)->create([
            'borrow_request_id' => $request->id, 'status' => BorrowItemStatus::Lent, 'returned_quantity' => 2,
        ]);

        expect($request->overview->status)->toBe(BorrowRequestStatus::PartiallyReturned);
    });

    it('可以直接用狀態篩選與分頁', function () {
        requestWith([BorrowItemStatus::Pending]);
        requestWith([BorrowItemStatus::Lent], ['borrow_date' => today()->subDays(5), 'due_date' => today()->subDay()]);
        requestWith([BorrowItemStatus::Lent], ['borrow_date' => today()->subDays(5), 'due_date' => today()->subDays(2)]);

        $page = BorrowRequestOverview::whereStatus(BorrowRequestStatus::Overdue)->paginate(1);

        expect($page->total())->toBe(2)->and($page->items())->toHaveCount(1);
    });

    it('overview 是唯讀的', function () {
        $overview = requestWith([BorrowItemStatus::Pending])->overview;
        $overview->remark = 'x';
        $overview->save();
    })->throws(LogicException::class);

    it('資料庫拒絕歸還日早於借用日', function () {
        BorrowRequest::factory()->create(['borrow_date' => '2026-11-20', 'due_date' => '2026-11-19']);
    })->throws(QueryException::class);
});

describe('開放借用期間', function () {
    it('always_open：一律開放，期間設定完全不看', function () {
        Setting::put('borrow.open_mode', BorrowOpenMode::AlwaysOpen->value);
        BorrowPeriod::create(['name' => '寒假', 'starts_on' => '2027-01-20', 'ends_on' => '2027-02-15', 'is_open' => false]);

        expect(BorrowPeriod::isOpenOn(Carbon\Carbon::parse('2027-01-25')))->toBeTrue()
            ->and(BorrowPeriod::isOpenOn(Carbon\Carbon::parse('2027-03-01')))->toBeTrue()
            ->and(BorrowPeriod::closedDatesBetween(
                Carbon\Carbon::parse('2027-01-20'),
                Carbon\Carbon::parse('2027-01-25'),
            ))->toBe([]);
    });

    it('periods：只有設為開放的期間內才算開放，期間外一律關閉', function () {
        Setting::put('borrow.open_mode', BorrowOpenMode::Periods->value);
        BorrowPeriod::create(['name' => '寒假', 'starts_on' => '2027-01-20', 'ends_on' => '2027-02-15', 'is_open' => false]);
        BorrowPeriod::create(['name' => '開學', 'starts_on' => '2027-02-16', 'ends_on' => '2027-06-30', 'is_open' => true]);

        expect(BorrowPeriod::isOpenOn(Carbon\Carbon::parse('2027-01-20')))->toBeFalse()
            ->and(BorrowPeriod::isOpenOn(Carbon\Carbon::parse('2027-02-15')))->toBeFalse()
            ->and(BorrowPeriod::isOpenOn(Carbon\Carbon::parse('2027-02-16')))->toBeTrue()
            // 沒有任何期間涵蓋 → 關閉
            ->and(BorrowPeriod::isOpenOn(Carbon\Carbon::parse('2027-07-01')))->toBeFalse();
    });

    it('值勤時間模式還沒接 API，不能被選取', function () {
        expect(BorrowOpenMode::DutyHours->available())->toBeFalse()
            ->and(BorrowOpenMode::selectableValues())
            ->toBe([BorrowOpenMode::AlwaysOpen->value, BorrowOpenMode::Periods->value]);
    });

    it('期間不能重疊', function () {
        BorrowPeriod::create(['name' => '寒假', 'starts_on' => '2027-01-20', 'ends_on' => '2027-02-15', 'is_open' => false]);
        BorrowPeriod::create(['name' => '迎新', 'starts_on' => '2027-02-15', 'ends_on' => '2027-02-20', 'is_open' => true]);
    })->throws(QueryException::class);
});

describe('財產編號', function () {
    beforeEach(function () {
        $category = PropertyCategory::factory()->create(['code' => '200']);
        $this->sub = PropertySubcategory::factory()->create(['property_category_id' => $category->id, 'code' => '0']);
    });

    it('依地點＋主類＋次類產生下一個流水號', function () {
        expect(PropertyCode::next(loc('jinde'), $this->sub))->toBe('J2000001');

        Property::factory()->create(['ssid' => 'J2000001']);
        Property::factory()->create(['ssid' => 'J2000007']);
        Property::factory()->create(['ssid' => 'B2000099']); // 不同地點不影響

        expect(PropertyCode::next(loc('jinde'), $this->sub))->toBe('J2000008')
            ->and(PropertyCode::next(loc('baosan'), $this->sub))->toBe('B2000100');
    });

    it('流水號用完時要求另起類別', function () {
        Property::factory()->create(['ssid' => 'J2000999']);

        PropertyCode::next(loc('jinde'), $this->sub);
    })->throws(RuntimeException::class, '另起類別編號');

    it('資料庫拒絕格式錯誤的財產編號', function (string $ssid) {
        Property::factory()->create(['ssid' => $ssid]);
    })->with(['j2000001', 'J2A00001', 'J20000', 'J2000-01'])->throws(QueryException::class);

    it('可以拆解財產編號', function () {
        expect(PropertyCode::parse('3200A012'))->toBe(['location' => '3', 'category' => '200', 'subcategory' => 'A', 'serial' => 12])
            ->and(PropertyCode::parse('bad'))->toBeNull();
    });
});
