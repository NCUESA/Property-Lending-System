<?php

use App\Enums\BorrowItemStatus;
use App\Enums\BorrowRequestStatus;
use App\Http\Controllers\BorrowRequestController;
use App\Models\BorrowPeriod;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestItem;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyPool;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserGroup;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SettingSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([LocationSeeder::class, SettingSeeder::class]);
    $this->jinde = Location::where('code', 'jinde')->firstOrFail();
    $this->staff = User::factory()->staff()->create();
});

function borrowPayload(array $overrides = []): array
{
    return [
        'location_id' => Location::where('code', 'jinde')->value('id'),
        'borrower_department' => '吉他社',
        'borrower_name' => '王小明',
        'phone' => '0912345678',
        'email' => 'ming@example.com',
        'borrow_date' => today()->toDateString(),
        'due_date' => today()->addDays(3)->toDateString(),
        'agreed_terms' => true,
        'property_ids' => [],
        ...$overrides,
    ];
}

describe('日曆', function () {
    it('不開放預借時借用日只到今天，歸還日還能往後 maxDays − 1 天', function () {
        Setting::put('borrow.max_days', 5);
        Setting::put('borrow.allow_advance', false);

        $this->get(route('borrow.create'))->assertInertia(fn (Assert $page) => $page
            ->where('calendar.today', today()->toDateString())
            ->where('calendar.lastBorrowDate', today()->toDateString())
            ->where('calendar.lastDate', today()->addDays(4)->toDateString())
        );
    });

    it('開放預借時借用日可到 horizon', function () {
        Setting::put('borrow.allow_advance', true);
        $horizon = BorrowRequestController::CALENDAR_HORIZON_DAYS;

        $this->get(route('borrow.create'))->assertInertia(fn (Assert $page) => $page
            ->where('calendar.lastBorrowDate', today()->addDays($horizon)->toDateString())
        );
    });

    it('暫停借用的日子會送到前端標成不可選', function () {
        Setting::put('borrow.allow_advance', true);
        BorrowPeriod::create(['name' => '校慶', 'starts_on' => today()->addDays(2), 'ends_on' => today()->addDays(4), 'is_open' => false]);

        $this->get(route('borrow.create'))->assertInertia(fn (Assert $page) => $page
            ->where('calendar.closedDates', [
                today()->addDays(2)->toDateString(),
                today()->addDays(3)->toDateString(),
                today()->addDays(4)->toDateString(),
            ])
        );
    });

    it('預設不開放借用時，期間外的每一天都算暫停', function () {
        Setting::put('borrow.default_open', false);
        Setting::put('borrow.max_days', 3);
        Setting::put('borrow.allow_advance', false);
        BorrowPeriod::create(['name' => '開放週', 'starts_on' => today(), 'ends_on' => today()->addDay(), 'is_open' => true]);

        // 視窗是今天 ~ 今天+2，其中只有前兩天在開放期間內
        $this->get(route('borrow.create'))->assertInertia(fn (Assert $page) => $page
            ->where('calendar.closedDates', [today()->addDays(2)->toDateString()])
        );
    });
});

describe('申請', function () {
    it('訪客可以申請 auth_level 0 的器材', function () {
        $properties = Property::factory()->count(2)->create(['location_id' => $this->jinde->id]);

        $this->post(route('borrow.store'), borrowPayload(['property_ids' => $properties->pluck('id')->all()]))
            ->assertRedirect(route('borrow.create', ['location' => 'jinde']))
            ->assertInertiaFlash('toast.type', 'success');

        $borrow = BorrowRequest::sole();
        expect($borrow->items)->toHaveCount(2)
            ->and($borrow->user_id)->toBeNull()
            ->and($borrow->overview->status)->toBe(BorrowRequestStatus::Pending);
    });

    it('借用頁只列出可借、可從此地點取用、且有權限的器材', function () {
        $ok = Property::factory()->create(['location_id' => $this->jinde->id]);
        $fromWarehouse = Property::factory()->create(['location_id' => Location::where('code', '307')->value('id')]);
        Property::factory()->create(['location_id' => Location::where('code', 'baosan')->value('id')]);
        Property::factory()->restricted()->create(['location_id' => $this->jinde->id]);
        Property::factory()->notLendable()->create(['location_id' => $this->jinde->id]);
        $lent = Property::factory()->create(['location_id' => $this->jinde->id]);
        BorrowRequestItem::factory()->create(['property_id' => $lent->id, 'status' => BorrowItemStatus::Lent]);

        $this->get(route('borrow.create', ['location' => 'jinde']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Borrow/Create')
                ->where('properties', fn ($list) => collect($list)->pluck('id')->sort()->values()->all() === collect([$ok->id, $fromWarehouse->id])->sort()->values()->all())
                ->where('locations', fn ($list) => collect($list)->pluck('code')->contains('jinde') && ! collect($list)->pluck('code')->contains('307')));
    });

    it('需要授權的器材，訪客會被擋下並提示登入', function () {
        $restricted = Property::factory()->restricted()->create(['location_id' => $this->jinde->id]);

        $this->post(route('borrow.store'), borrowPayload(['property_ids' => [$restricted->id]]))
            ->assertSessionHasErrors(['property_ids' => "以下器材無法借用或需要登入：{$restricted->ssid}"]);

        expect(BorrowRequest::count())->toBe(0);
    });

    it('登入且群組有授權就可以借，並記錄借用人帳號', function () {
        $restricted = Property::factory()->restricted()->create(['location_id' => $this->jinde->id]);
        $user = User::factory()->create();
        $group = UserGroup::factory()->create();
        $group->members()->attach($user);
        $group->grants()->create(['property_id' => $restricted->id]);

        $this->actingAs($user)
            ->post(route('borrow.store'), borrowPayload(['property_ids' => [$restricted->id]]))
            ->assertSessionHasNoErrors();

        expect(BorrowRequest::sole()->user_id)->toBe($user->id);
    });

    it('器材剛被別人借走時回傳表單錯誤，而不是 500', function () {
        $property = Property::factory()->create(['location_id' => $this->jinde->id]);
        BorrowRequestItem::factory()->create(['property_id' => $property->id]);

        $this->post(route('borrow.store'), borrowPayload(['property_ids' => [$property->id]]))
            ->assertSessionHasErrors(['property_ids' => '有器材剛剛被其他人借走了，請重新選擇。']);

        expect(BorrowRequest::count())->toBe(1); // 只有原本那筆，交易已回滾
    });

    it('bulk 數量不足時回傳表單錯誤', function () {
        $pool = PropertyPool::factory()->create(['location_id' => $this->jinde->id]);
        Property::factory()->count(3)->inPool($pool)->create();

        $this->post(route('borrow.store'), borrowPayload(['pools' => [['id' => $pool->id, 'quantity' => 4]]]))
            ->assertSessionHasErrors('pools');

        $this->post(route('borrow.store'), borrowPayload(['pools' => [['id' => $pool->id, 'quantity' => 3]]]))
            ->assertSessionHasNoErrors();
    });

    it('品項池裡的器材不能單獨挑選', function () {
        $pool = PropertyPool::factory()->create(['location_id' => $this->jinde->id]);
        $chair = Property::factory()->inPool($pool)->create();

        $this->post(route('borrow.store'), borrowPayload(['property_ids' => [$chair->id]]))
            ->assertSessionHasErrors('property_ids');
    });

    it('不能跨取用地點借用', function () {
        $baosan = Property::factory()->create(['location_id' => Location::where('code', 'baosan')->value('id')]);

        $this->post(route('borrow.store'), borrowPayload(['property_ids' => [$baosan->id]]))
            ->assertSessionHasErrors('property_ids');
    });

    it('後端強制執行日期規則（舊系統只有前端檢查）', function (array $dates, string $field) {
        $property = Property::factory()->create(['location_id' => $this->jinde->id]);

        $this->post(route('borrow.store'), borrowPayload(['property_ids' => [$property->id], ...$dates]))
            ->assertSessionHasErrors($field);
    })->with([
        '不開放預借' => [['borrow_date' => today()->addDay()->toDateString(), 'due_date' => today()->addDays(2)->toDateString()], 'borrow_date'],
        '借用日不能是過去' => [['borrow_date' => today()->subDay()->toDateString()], 'borrow_date'],
        '最長 7 天（含借用當天）' => [['due_date' => today()->addDays(7)->toDateString()], 'due_date'],
        '歸還日早於借用日' => [['due_date' => today()->subDay()->toDateString()], 'due_date'],
    ]);

    it('第 7 天歸還可以', function () {
        $property = Property::factory()->create(['location_id' => $this->jinde->id]);

        $this->post(route('borrow.store'), borrowPayload(['property_ids' => [$property->id], 'due_date' => today()->addDays(6)->toDateString()]))
            ->assertSessionHasNoErrors();
    });

    it('開放預借時可以選未來日期', function () {
        Setting::put('borrow.allow_advance', true);
        $property = Property::factory()->create(['location_id' => $this->jinde->id]);

        $this->post(route('borrow.store'), borrowPayload([
            'property_ids' => [$property->id],
            'borrow_date' => today()->addDays(2)->toDateString(),
            'due_date' => today()->addDays(4)->toDateString(),
        ]))->assertSessionHasNoErrors();
    });

    it('暫停借用期間不能申請', function () {
        BorrowPeriod::create(['name' => '寒假', 'starts_on' => today()->subDay(), 'ends_on' => today()->addDays(10), 'is_open' => false]);
        $property = Property::factory()->create(['location_id' => $this->jinde->id]);

        $this->post(route('borrow.store'), borrowPayload(['property_ids' => [$property->id]]))
            ->assertSessionHasErrors(['borrow_date' => '此期間暫停借用，無法於這天借用，請留意學生會公告。']);
    });

    it('歸還日落在暫停借用期間也不能申請', function () {
        Setting::put('borrow.allow_advance', true);
        // 取件日開放、歸還日休館
        BorrowPeriod::create(['name' => '寒假', 'starts_on' => today()->addDays(3), 'ends_on' => today()->addDays(10), 'is_open' => false]);
        $property = Property::factory()->create(['location_id' => $this->jinde->id]);

        $this->post(route('borrow.store'), borrowPayload([
            'property_ids' => [$property->id],
            'borrow_date' => today()->toDateString(),
            'due_date' => today()->addDays(3)->toDateString(),
        ]))->assertSessionHasErrors(['due_date' => '此期間暫停借用，無法於這天歸還，請留意學生會公告。']);
    });

    it('借用期間中間跨到休館日沒關係', function () {
        Setting::put('borrow.allow_advance', true);
        BorrowPeriod::create(['name' => '校慶', 'starts_on' => today()->addDays(2), 'ends_on' => today()->addDays(2), 'is_open' => false]);
        $property = Property::factory()->create(['location_id' => $this->jinde->id]);

        $this->post(route('borrow.store'), borrowPayload([
            'property_ids' => [$property->id],
            'borrow_date' => today()->toDateString(),
            'due_date' => today()->addDays(4)->toDateString(),
        ]))->assertSessionHasNoErrors();
    });

    it('必須同意注意事項、至少選一項器材', function () {
        $this->post(route('borrow.store'), borrowPayload(['agreed_terms' => false]))
            ->assertSessionHasErrors(['agreed_terms', 'property_ids']);
    });

    it('驗證訊息是中文', function () {
        $this->post(route('borrow.store'), borrowPayload(['borrower_name' => '']))
            ->assertSessionHasErrors(['borrower_name' => '請填寫聯絡人姓名。']);
    });
});

describe('出借與歸還', function () {
    beforeEach(function () {
        $this->mic = Property::factory()->create(['location_id' => $this->jinde->id]);
        $this->cable = Property::factory()->create(['location_id' => $this->jinde->id]);
        $this->pool = PropertyPool::factory()->create(['location_id' => $this->jinde->id]);
        Property::factory()->count(10)->inPool($this->pool)->create();

        $this->post(route('borrow.store'), borrowPayload([
            'property_ids' => [$this->mic->id, $this->cable->id],
            'pools' => [['id' => $this->pool->id, 'quantity' => 6]],
        ]))->assertSessionHasNoErrors();

        $this->borrow = BorrowRequest::sole();
        $this->items = $this->borrow->items->keyBy(fn ($i) => $i->property_id ?? 'pool');
    });

    it('申請 → 部分出借 → 部分歸還 → 全部歸還', function () {
        // 出借：麥克風 + 椅子少給 1 張；延長線沒拿走 → 自動退回
        $this->actingAs($this->staff)
            ->post(route('admin.borrows.lend', $this->borrow), [
                'items' => [['id' => $this->items[$this->mic->id]->id], ['id' => $this->items['pool']->id, 'quantity' => 5]],
                'id_card_taken' => true,
                'deposit_taken' => true,
                'deposit_box_no' => 12,
            ])
            ->assertSessionHasNoErrors();

        $this->borrow->refresh();
        expect($this->borrow->lent_by)->toBe($this->staff->id)
            ->and($this->borrow->deposit_amount)->toBe(100)
            ->and($this->items[$this->cable->id]->fresh()->status)->toBe(BorrowItemStatus::Cancelled)
            ->and($this->items['pool']->fresh()->quantity)->toBe(5)
            ->and($this->borrow->overview->status)->toBe(BorrowRequestStatus::Lent)
            ->and(PropertyPool::withAvailableQuantity()->find($this->pool->id)->available_quantity)->toBe(5);

        // 延長線被退回後別人可以借
        expect(Property::available()->whereKey($this->cable->id)->exists())->toBeTrue();

        // 部分歸還：椅子先還 2 張
        $this->actingAs($this->staff)
            ->post(route('admin.borrows.return', $this->borrow), ['items' => [['id' => $this->items['pool']->id, 'quantity' => 2]]])
            ->assertSessionHasNoErrors();

        expect($this->borrow->fresh()->overview->status)->toBe(BorrowRequestStatus::PartiallyReturned)
            ->and($this->borrow->fresh()->returned_at)->toBeNull();

        // 全部歸還 + 退證件押金
        $this->actingAs($this->staff)
            ->post(route('admin.borrows.return', $this->borrow), [
                'items' => [['id' => $this->items[$this->mic->id]->id], ['id' => $this->items['pool']->id]],
                'id_card_returned' => true,
                'deposit_returned' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->borrow->refresh();
        expect($this->borrow->overview->status)->toBe(BorrowRequestStatus::Returned)
            ->and($this->borrow->returned_by)->toBe($this->staff->id)
            ->and($this->borrow->id_card_returned)->toBeTrue()
            ->and($this->items['pool']->fresh()->returned_quantity)->toBe(5)
            ->and(PropertyPool::withAvailableQuantity()->find($this->pool->id)->available_quantity)->toBe(10);
    });

    it('不能重複出借（舊系統會回 200 + success:false）', function () {
        $lend = fn () => $this->actingAs($this->staff)->post(route('admin.borrows.lend', $this->borrow), [
            'items' => [['id' => $this->items[$this->mic->id]->id]],
            'id_card_taken' => true,
            'deposit_taken' => false,
        ]);

        $lend()->assertSessionHasNoErrors();
        $lend()->assertSessionHasErrors(['items' => '這張借用單已經出借過了。']);
    });

    it('已經歸還的項目不能再歸還', function () {
        $this->actingAs($this->staff)->post(route('admin.borrows.lend', $this->borrow), [
            'items' => [['id' => $this->items[$this->mic->id]->id]], 'id_card_taken' => true, 'deposit_taken' => false,
        ]);
        $return = fn () => $this->actingAs($this->staff)->post(route('admin.borrows.return', $this->borrow), [
            'items' => [['id' => $this->items[$this->mic->id]->id]],
        ]);

        $return()->assertSessionHasNoErrors();
        $return()->assertSessionHasErrors(['items' => '選取的項目不是借出中的狀態，可能已經歸還過了。']);
    });

    it('bulk 出借數量不能超過申請數量', function () {
        $this->actingAs($this->staff)->post(route('admin.borrows.lend', $this->borrow), [
            'items' => [['id' => $this->items['pool']->id, 'quantity' => 7]], 'id_card_taken' => true, 'deposit_taken' => false,
        ])->assertSessionHasErrors('items');
    });

    it('不能操作別張借用單的明細', function () {
        $other = BorrowRequestItem::factory()->create();

        $this->actingAs($this->staff)->post(route('admin.borrows.lend', $this->borrow), [
            'items' => [['id' => $other->id]], 'id_card_taken' => true, 'deposit_taken' => false,
        ])->assertSessionHasErrors('items');
    });

    it('整張退回會釋出所有器材並記錄原因', function () {
        $this->actingAs($this->staff)
            ->post(route('admin.borrows.cancel', $this->borrow), ['reason' => '證件未帶'])
            ->assertSessionHasNoErrors();

        expect($this->borrow->fresh()->overview->status)->toBe(BorrowRequestStatus::Cancelled)
            ->and($this->borrow->fresh()->remark)->toContain('證件未帶')
            ->and(Property::available()->whereKey([$this->mic->id, $this->cable->id])->count())->toBe(2);
    });

    it('已出借的單不能整張退回', function () {
        $this->actingAs($this->staff)->post(route('admin.borrows.lend', $this->borrow), [
            'items' => [['id' => $this->items[$this->mic->id]->id]], 'id_card_taken' => true, 'deposit_taken' => false,
        ]);

        $this->actingAs($this->staff)
            ->post(route('admin.borrows.cancel', $this->borrow))
            ->assertSessionHasErrors('reason');
    });

    it('可以修改備註與延長歸還日', function () {
        $this->actingAs($this->staff)
            ->patch(route('admin.borrows.update', $this->borrow), ['remark' => '延長借用', 'due_date' => today()->addDays(10)->toDateString()])
            ->assertSessionHasNoErrors();

        expect($this->borrow->fresh()->remark)->toBe('延長借用');
    });

    it('列表可依狀態篩選並分頁', function () {
        $this->actingAs($this->staff)
            ->get(route('admin.borrows.index', ['status' => ['pending']]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Borrows/Index')
                ->has('borrows.data', 1)
                ->where('borrows.data.0.status', 'pending'));

        $this->actingAs($this->staff)
            ->get(route('admin.borrows.index', ['status' => ['returned']]))
            ->assertInertia(fn (Assert $page) => $page->has('borrows.data', 0));
    });

    it('明細頁帶出即時狀態與明細', function () {
        $this->actingAs($this->staff)
            ->get(route('admin.borrows.show', $this->borrow))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Borrows/Show')
                ->where('borrow.status', 'pending')
                ->has('borrow.items', 3));
    });
});

describe('展延歸還日', function () {
    beforeEach(function () {
        $this->mic = Property::factory()->create(['location_id' => $this->jinde->id]);
        $this->post(route('borrow.store'), borrowPayload([
            'property_ids' => [$this->mic->id],
        ]))->assertSessionHasNoErrors();
        $this->borrow = BorrowRequest::sole();
    });

    it('展延要填理由，並記下經手人與時間', function () {
        $newDue = $this->borrow->due_date->copy()->addDays(3)->toDateString();

        $this->actingAs($this->staff)
            ->post(route('admin.borrows.extend', $this->borrow), [
                'due_date' => $newDue,
                'reason' => '活動延期到下週',
            ])->assertSessionHasNoErrors();

        $this->borrow->refresh();

        expect($this->borrow->due_date->toDateString())->toBe($newDue)
            ->and($this->borrow->extension_reason)->toBe('活動延期到下週')
            ->and($this->borrow->extended_by)->toBe($this->staff->id)
            ->and($this->borrow->extended_at)->not->toBeNull();
    });

    it('沒填理由不給展延', function () {
        $this->actingAs($this->staff)
            ->post(route('admin.borrows.extend', $this->borrow), [
                'due_date' => $this->borrow->due_date->copy()->addDay()->toDateString(),
            ])->assertSessionHasErrors('reason');
    });

    it('只能往後延', function () {
        $this->actingAs($this->staff)
            ->post(route('admin.borrows.extend', $this->borrow), [
                'due_date' => $this->borrow->due_date->copy()->subDay()->toDateString(),
                'reason' => '想改早',
            ])->assertSessionHasErrors('due_date');
    });

    it('展延後提醒信會依新的日期重寄', function () {
        $this->borrow->forceFill(['due_reminder_sent_at' => now()])->save();

        $this->actingAs($this->staff)
            ->post(route('admin.borrows.extend', $this->borrow), [
                'due_date' => $this->borrow->due_date->copy()->addDays(3)->toDateString(),
                'reason' => '延期',
            ])->assertSessionHasNoErrors();

        expect($this->borrow->refresh()->due_reminder_sent_at)->toBeNull();
    });

    it('備註端點不再能改歸還日', function () {
        $original = $this->borrow->due_date->toDateString();

        $this->actingAs($this->staff)
            ->patch(route('admin.borrows.update', $this->borrow), [
                'remark' => '改備註',
                'due_date' => $this->borrow->due_date->copy()->addDays(10)->toDateString(),
            ])->assertSessionHasNoErrors();

        $this->borrow->refresh();

        expect($this->borrow->remark)->toBe('改備註')
            ->and($this->borrow->due_date->toDateString())->toBe($original);
    });
});

describe('押金箱號', function () {
    beforeEach(function () {
        $this->mic = Property::factory()->create(['location_id' => $this->jinde->id]);
        $this->post(route('borrow.store'), borrowPayload([
            'property_ids' => [$this->mic->id],
        ]))->assertSessionHasNoErrors();
        $this->borrow = BorrowRequest::sole();
        $this->itemId = $this->borrow->items->first()->id;
    });

    it('箱號上限跟著後台設定走', function () {
        Setting::put('borrow.deposit_box_count', 20);

        $payload = fn (int $box) => [
            'items' => [['id' => $this->itemId]],
            'id_card_taken' => true,
            'deposit_taken' => true,
            'deposit_box_no' => $box,
        ];

        $this->actingAs($this->staff)
            ->post(route('admin.borrows.lend', $this->borrow), $payload(21))
            ->assertSessionHasErrors('deposit_box_no');

        $this->actingAs($this->staff)
            ->post(route('admin.borrows.lend', $this->borrow), $payload(20))
            ->assertSessionHasNoErrors();

        expect($this->borrow->refresh()->deposit_box_no)->toBe(20);
    });

    it('明細頁會帶出箱數給前端', function () {
        Setting::put('borrow.deposit_box_count', 12);

        $this->actingAs($this->staff)
            ->get(route('admin.borrows.show', $this->borrow))
            ->assertInertia(fn (Assert $page) => $page->where('depositBoxCount', 12));
    });
});

describe('排程：逾期未取', function () {
    it('借用日已過仍未取件的申請會被退回並釋出器材', function () {
        $property = Property::factory()->create(['location_id' => $this->jinde->id]);
        $stale = BorrowRequest::factory()->create(['borrow_date' => today()->subDays(2), 'due_date' => today()]);
        BorrowRequestItem::factory()->create(['borrow_request_id' => $stale->id, 'property_id' => $property->id]);
        $fresh = BorrowRequestItem::factory()->create();

        $this->artisan('borrow:expire-pending')->expectsOutputToContain('已退回 1 筆')->assertSuccessful();

        expect(Property::available()->whereKey($property->id)->exists())->toBeTrue()
            ->and($fresh->fresh()->status)->toBe(BorrowItemStatus::Pending);
    });
});

describe('公開器材狀態', function () {
    it('不會洩漏借用人姓名、電話、email', function () {
        $property = Property::factory()->create(['location_id' => $this->jinde->id]);
        $borrow = BorrowRequest::factory()->create(['borrower_name' => '機密姓名', 'phone' => '0900000000', 'email' => 'secret@example.com']);
        BorrowRequestItem::factory()->create(['borrow_request_id' => $borrow->id, 'property_id' => $property->id, 'status' => BorrowItemStatus::Lent]);

        $response = $this->get(route('properties.status'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Properties/Status')
            ->where('properties.data.0.borrow.department', $borrow->borrower_department));
        expect($response->getContent())
            ->not->toContain('機密姓名')
            ->not->toContain('0900000000')
            ->not->toContain('secret@example.com');
    });

    it('可以篩選借出中的器材', function () {
        Property::factory()->create(['location_id' => $this->jinde->id]);
        $lent = Property::factory()->create(['location_id' => $this->jinde->id]);
        BorrowRequestItem::factory()->create(['property_id' => $lent->id, 'status' => BorrowItemStatus::Lent]);

        $this->get(route('properties.status', ['availability' => 'borrowed']))
            ->assertInertia(fn (Assert $page) => $page->has('properties.data', 1)->where('properties.data.0.id', $lent->id));
    });
});

describe('序列化', function () {
    it('日期欄位以 Y-m-d 傳給前端，不受時區轉換影響', function () {
        $borrow = BorrowRequest::factory()->create(['borrow_date' => '2026-11-20', 'due_date' => '2026-11-22']);

        expect($borrow->fresh()->toArray())
            ->borrow_date->toBe('2026-11-20')
            ->due_date->toBe('2026-11-22');
    });
});
