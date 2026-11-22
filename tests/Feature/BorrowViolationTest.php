<?php

use App\Actions\Borrow\ExpirePendingItems;
use App\Actions\Borrow\RecordOverdueViolations;
use App\Enums\BorrowItemStatus;
use App\Enums\BorrowRequestStatus;
use App\Enums\BorrowViolationKind;
use App\Models\BorrowBlock;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestItem;
use App\Models\BorrowViolation;
use App\Models\Location;
use App\Models\Property;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SettingSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([LocationSeeder::class, SettingSeeder::class]);
    $this->jinde = Location::where('code', 'jinde')->firstOrFail();
    $this->borrower = User::factory()->create(['student_id' => 'S1234567']);
});

/** 建一張屬於 $this->borrower 的待取件借用單。 */
function pendingRequest(array $overrides = []): BorrowRequest
{
    $request = BorrowRequest::factory()->create([
        'location_id' => test()->jinde->id,
        'user_id' => test()->borrower->id,
        'borrower_name' => '王小明',
        'phone' => '0912345678',
        'borrow_date' => today(),
        'due_date' => today()->addDays(3),
        'lent_at' => null,
        'returned_at' => null,
        ...$overrides,
    ]);

    BorrowRequestItem::factory()->create([
        'borrow_request_id' => $request->id,
        'property_id' => Property::factory()->create(['location_id' => test()->jinde->id])->id,
        'status' => BorrowItemStatus::Pending,
    ]);

    return $request;
}

describe('我的借用', function () {
    it('只看得到自己的借用單', function () {
        $mine = pendingRequest();
        $other = pendingRequest(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->borrower)
            ->get(route('my.borrows.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Borrow/MyBorrows')
                ->has('active', 1)
                ->where('active.0.id', $mine->id)
                ->where('active.0.cancellable', true));

        expect($other->id)->not->toBe($mine->id);
    });

    it('訪客看不到，要先登入', function () {
        $this->get(route('my.borrows.index'))->assertRedirect(route('login'));
    });

    it('已結束的單進歷史紀錄，不能取消', function () {
        $returned = pendingRequest(['lent_at' => now()->subDays(2), 'returned_at' => now()]);
        $returned->items()->update(['status' => BorrowItemStatus::Returned, 'returned_at' => now()]);

        $this->actingAs($this->borrower)
            ->get(route('my.borrows.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('active', 0)
                ->has('history.data', 1)
                ->where('history.data.0.cancellable', false));
    });

    it('不外流押金箱號、證件狀態與電話', function () {
        pendingRequest();

        $json = $this->actingAs($this->borrower)
            ->get(route('my.borrows.index'))
            ->getContent();

        expect($json)->not->toContain('deposit_box_no')
            ->and($json)->not->toContain('id_card_taken');
    });
});

describe('自行取消', function () {
    it('取消會退回明細、釋出器材，並留下違規紀錄', function () {
        $request = pendingRequest();
        $property = $request->items()->first()->property;

        $this->actingAs($this->borrower)
            ->post(route('my.borrows.cancel', $request), ['reason' => '活動取消'])
            ->assertSessionHasNoErrors();

        expect($request->items()->first()->status)->toBe(BorrowItemStatus::Cancelled)
            ->and(Property::available()->whereKey($property->id)->exists())->toBeTrue()
            ->and($request->fresh()->remark)->toContain('由借用人自行取消')
            ->and($request->fresh()->remark)->toContain('活動取消');

        $violation = BorrowViolation::sole();
        expect($violation->kind)->toBe(BorrowViolationKind::SelfCancel)
            ->and($violation->student_id)->toBe('S1234567')
            ->and($violation->phone)->toBe('0912345678');
    });

    it('不能取消別人的單', function () {
        $other = pendingRequest(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->borrower)
            ->post(route('my.borrows.cancel', $other))
            ->assertSessionHasErrors('reason');

        expect(BorrowViolation::count())->toBe(0)
            ->and($other->items()->first()->status)->toBe(BorrowItemStatus::Pending);
    });

    it('已出借的單不能自行取消', function () {
        $request = pendingRequest(['lent_at' => now()]);

        $this->actingAs($this->borrower)
            ->post(route('my.borrows.cancel', $request))
            ->assertSessionHasErrors('reason');

        expect(BorrowViolation::count())->toBe(0);
    });

    it('值班人員代為退回不算違規', function () {
        $request = pendingRequest();

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('admin.borrows.cancel', $request), ['reason' => '器材壞了'])
            ->assertSessionHasNoErrors();

        expect(BorrowViolation::count())->toBe(0);
    });
});

describe('自動封鎖', function () {
    it('同一天取消達上限就封鎖到當天結束', function () {
        $first = pendingRequest();
        $second = pendingRequest();

        $this->actingAs($this->borrower)
            ->post(route('my.borrows.cancel', $first))
            ->assertSessionHasNoErrors();

        expect(BorrowBlock::count())->toBe(0); // 第 1 次還不封

        $this->actingAs($this->borrower)
            ->post(route('my.borrows.cancel', $second))
            ->assertSessionHasNoErrors();

        $block = BorrowBlock::sole();
        expect($block->student_id)->toBe('S1234567')
            ->and($block->created_by)->toBeNull()
            ->and($block->reason)->toContain('2 次')
            ->and($block->blocked_until->toDateString())->toBe(today()->toDateString())
            ->and($block->blocked_until->format('H:i'))->toBe('23:59');
    });

    it('上限可以從後台調整', function () {
        Setting::put(BorrowViolationKind::SelfCancel->settingKey('limit'), 1);
        $request = pendingRequest();

        $this->actingAs($this->borrower)->post(route('my.borrows.cancel', $request));

        expect(BorrowBlock::count())->toBe(1);
    });

    it('封鎖天數可以從後台調整', function () {
        Setting::put(BorrowViolationKind::SelfCancel->settingKey('limit'), 1);
        Setting::put(BorrowViolationKind::SelfCancel->settingKey('block_days'), 3);

        $this->actingAs($this->borrower)->post(route('my.borrows.cancel', pendingRequest()));

        expect(BorrowBlock::sole()->blocked_until->toDateString())
            ->toBe(today()->addDays(2)->toDateString());
    });

    it('被封鎖就送不出新的借用申請', function () {
        Setting::put(BorrowViolationKind::SelfCancel->settingKey('limit'), 1);
        $this->actingAs($this->borrower)->post(route('my.borrows.cancel', pendingRequest()));

        $property = Property::factory()->create(['location_id' => $this->jinde->id]);

        $this->actingAs($this->borrower)->post(route('borrow.store'), [
            'location_id' => $this->jinde->id,
            'borrower_department' => '封鎖測試單位', // factory 不會產生這個值
            'borrower_name' => '王小明',
            'phone' => '0912345678',
            'email' => 'ming@example.com',
            'borrow_date' => today()->toDateString(),
            'due_date' => today()->addDays(2)->toDateString(),
            'agreed_terms' => true,
            'property_ids' => [$property->id],
        ])->assertSessionHasErrors('phone');

        expect(BorrowRequest::where('borrower_department', '封鎖測試單位')->exists())->toBeFalse();
    });

    it('換手機號碼也擋得住，因為學號相同', function () {
        Setting::put(BorrowViolationKind::SelfCancel->settingKey('limit'), 1);
        $this->actingAs($this->borrower)->post(route('my.borrows.cancel', pendingRequest()));

        $property = Property::factory()->create(['location_id' => $this->jinde->id]);

        $this->actingAs($this->borrower)->post(route('borrow.store'), [
            'location_id' => $this->jinde->id,
            'borrower_department' => '封鎖測試單位', // factory 不會產生這個值
            'borrower_name' => '王小明',
            'phone' => '0900000000', // 換號碼
            'email' => 'ming@example.com',
            'borrow_date' => today()->toDateString(),
            'due_date' => today()->addDays(2)->toDateString(),
            'agreed_terms' => true,
            'property_ids' => [$property->id],
        ])->assertSessionHasErrors('phone');

        expect(BorrowRequest::where('borrower_department', '封鎖測試單位')->exists())->toBeFalse();
    });

    it('沒登入的訪客用同一支手機也擋得住', function () {
        Setting::put(BorrowViolationKind::SelfCancel->settingKey('limit'), 1);
        $this->actingAs($this->borrower)->post(route('my.borrows.cancel', pendingRequest()));

        $property = Property::factory()->create(['location_id' => $this->jinde->id]);

        $this->post(route('borrow.store'), [
            'location_id' => $this->jinde->id,
            'borrower_department' => '封鎖測試單位', // factory 不會產生這個值
            'borrower_name' => '王小明',
            'phone' => '0912345678',
            'email' => 'ming@example.com',
            'borrow_date' => today()->toDateString(),
            'due_date' => today()->addDays(2)->toDateString(),
            'agreed_terms' => true,
            'property_ids' => [$property->id],
        ])->assertSessionHasErrors('phone');

        expect(BorrowRequest::where('borrower_department', '封鎖測試單位')->exists())->toBeFalse();
    });

    it('我的借用頁會顯示封鎖狀態', function () {
        Setting::put(BorrowViolationKind::SelfCancel->settingKey('limit'), 1);
        $this->actingAs($this->borrower)->post(route('my.borrows.cancel', pendingRequest()));

        $this->actingAs($this->borrower)
            ->get(route('my.borrows.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('block.reason', fn (string $r) => str_contains($r, '自行取消'))
                ->has('violations', 1));
    });
});

describe('後台解封', function () {
    beforeEach(function () {
        Setting::put(BorrowViolationKind::SelfCancel->settingKey('limit'), 1);
        $this->actingAs($this->borrower)->post(route('my.borrows.cancel', pendingRequest()));
        $this->admin = User::factory()->admin()->create();
    });

    it('解封要填原因，解完就能再借', function () {
        $block = BorrowBlock::sole();

        $this->actingAs($this->admin)
            ->post(route('admin.blocks.lift', $block), ['reason' => '已當面說明'])
            ->assertSessionHasNoErrors();

        $block->refresh();
        expect($block->lifted_at)->not->toBeNull()
            ->and($block->lifted_by)->toBe($this->admin->id)
            ->and($block->lift_reason)->toBe('已當面說明')
            ->and(BorrowBlock::active()->count())->toBe(0);
    });

    it('沒填原因不給解封', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.blocks.lift', BorrowBlock::sole()), [])
            ->assertSessionHasErrors('reason');

        expect(BorrowBlock::active()->count())->toBe(1);
    });

    it('解封後違規重新計次，不會一取消就被封回去', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.blocks.lift', BorrowBlock::sole()), ['reason' => '通融一次']);

        // 上限是 1，解封前已有 1 筆違規；若沒有重新計次，這次取消會立刻再封
        $this->actingAs($this->borrower)
            ->post(route('my.borrows.cancel', pendingRequest()))
            ->assertSessionHasNoErrors();

        expect(BorrowViolation::count())->toBe(2)
            ->and(BorrowBlock::active()->count())->toBe(1); // 第 2 次又達上限，合理
    });

    it('值班人員不能解封', function () {
        $this->actingAs(User::factory()->staff()->create())
            ->post(route('admin.blocks.lift', BorrowBlock::sole()), ['reason' => 'x'])
            ->assertForbidden();
    });
});

describe('違規紀錄清單', function () {
    it('可以用學號、手機或姓名搜尋，也可以依種類篩選', function () {
        $request = pendingRequest();
        $this->actingAs($this->borrower)->post(route('my.borrows.cancel', $request));

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.violations.index', ['q' => 'S1234567']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Violations/Index')
                ->has('violations.data', 1)
                ->where('violations.data.0.student_id', 'S1234567'));

        $this->actingAs($admin)
            ->get(route('admin.violations.index', ['q' => '0912345678']))
            ->assertInertia(fn (Assert $page) => $page->has('violations.data', 1));

        $this->actingAs($admin)
            ->get(route('admin.violations.index', ['kind' => 'overdue']))
            ->assertInertia(fn (Assert $page) => $page->has('violations.data', 0));
    });

    it('值班人員看得到，一般借用人看不到', function () {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('admin.violations.index'))->assertOk();

        $this->actingAs($this->borrower)
            ->get(route('admin.violations.index'))->assertForbidden();
    });
});

describe('排程產生的違規', function () {
    it('逾期未取會記一筆違規，但不封鎖', function () {
        pendingRequest(['borrow_date' => today()->subDay(), 'due_date' => today()]);

        app(ExpirePendingItems::class)->handle();

        $violation = BorrowViolation::sole();
        expect($violation->kind)->toBe(BorrowViolationKind::Expired)
            ->and($violation->student_id)->toBe('S1234567')
            ->and(BorrowBlock::count())->toBe(0);
    });

    it('逾期未還會記一筆違規', function () {
        $request = pendingRequest(['borrow_date' => today()->subDays(5), 'due_date' => today()->subDay()]);
        $request->items()->update(['status' => BorrowItemStatus::Lent]);
        $request->update(['lent_at' => now()->subDays(5)]);

        expect(app(RecordOverdueViolations::class)->handle())->toBe(1)
            ->and(BorrowViolation::sole()->kind)->toBe(BorrowViolationKind::Overdue);
    });

    it('排程重跑不會灌出重複紀錄', function () {
        $request = pendingRequest(['borrow_date' => today()->subDays(5), 'due_date' => today()->subDay()]);
        $request->items()->update(['status' => BorrowItemStatus::Lent]);
        $request->update(['lent_at' => now()->subDays(5)]);

        app(RecordOverdueViolations::class)->handle();

        expect(app(RecordOverdueViolations::class)->handle())->toBe(0)
            ->and(BorrowViolation::count())->toBe(1);
    });
});

describe('逾期歸還', function () {
    /** 建一張借出中、已經過了歸還日的單。 */
    function overdueRequest(): BorrowRequest
    {
        $request = pendingRequest([
            'borrow_date' => today()->subDays(5),
            'due_date' => today()->subDays(2),
            'lent_at' => now()->subDays(5),
        ]);
        $request->items()->update(['status' => BorrowItemStatus::Lent]);

        return $request;
    }

    it('遲還結案後狀態是逾期歸還', function () {
        $request = overdueRequest();

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('admin.borrows.return', $request), [
                'items' => [['id' => $request->items()->first()->id]],
            ])->assertSessionHasNoErrors();

        expect($request->fresh()->overview->status)->toBe(BorrowRequestStatus::ReturnedLate);
    });

    it('遲還會留下一筆違規紀錄', function () {
        $request = overdueRequest();

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('admin.borrows.return', $request), [
                'items' => [['id' => $request->items()->first()->id]],
            ]);

        $violation = BorrowViolation::sole();
        expect($violation->kind)->toBe(BorrowViolationKind::ReturnedLate)
            ->and($violation->student_id)->toBe('S1234567')
            ->and($violation->borrow_request_id)->toBe($request->id)
            ->and($violation->note)->toContain('應於');
    });

    it('準時歸還不算違規', function () {
        $request = pendingRequest([
            'borrow_date' => today()->subDays(2),
            'due_date' => today(),
            'lent_at' => now()->subDays(2),
        ]);
        $request->items()->update(['status' => BorrowItemStatus::Lent]);

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('admin.borrows.return', $request), [
                'items' => [['id' => $request->items()->first()->id]],
            ]);

        expect(BorrowViolation::count())->toBe(0)
            ->and($request->fresh()->overview->status)->toBe(BorrowRequestStatus::Returned);
    });

    it('逾期歸還會取代同一張單的逾期未還，不會記成兩筆', function () {
        $request = overdueRequest();

        // 排程先記了「逾期未還」
        expect(app(RecordOverdueViolations::class)->handle())->toBe(1);
        expect(BorrowViolation::sole()->kind)->toBe(BorrowViolationKind::Overdue);

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('admin.borrows.return', $request), [
                'items' => [['id' => $request->items()->first()->id]],
            ]);

        expect(BorrowViolation::count())->toBe(1)
            ->and(BorrowViolation::sole()->kind)->toBe(BorrowViolationKind::ReturnedLate);
    });

    it('預設不封鎖，但可以從後台打開', function () {
        $staff = User::factory()->staff()->create();

        $returnLate = function () use ($staff) {
            $request = overdueRequest();

            test()->actingAs($staff)
                ->post(route('admin.borrows.return', $request), [
                    'items' => [['id' => $request->items()->first()->id]],
                ])->assertSessionHasNoErrors();
        };

        $returnLate();
        expect(BorrowViolation::count())->toBe(1)
            ->and(BorrowBlock::count())->toBe(0); // 預設 limit 0 = 只記錄

        Setting::put(BorrowViolationKind::ReturnedLate->settingKey('limit'), 1);
        Setting::put(BorrowViolationKind::ReturnedLate->settingKey('block_days'), 5);

        $returnLate();

        $block = BorrowBlock::sole();
        expect($block->reason)->toContain('逾期歸還')
            ->and($block->blocked_until->toDateString())->toBe(today()->addDays(4)->toDateString());
    });
});

describe('調整封鎖期限', function () {
    beforeEach(function () {
        Setting::put(BorrowViolationKind::SelfCancel->settingKey('limit'), 1);
        $this->actingAs($this->borrower)->post(route('my.borrows.cancel', pendingRequest()));
        $this->admin = User::factory()->admin()->create();
        $this->block = BorrowBlock::sole();
    });

    it('可以延長到指定日期結束，並記下是誰改的', function () {
        $this->actingAs($this->admin)
            ->patch(route('admin.blocks.adjust', $this->block), [
                'until' => today()->addDays(6)->toDateString(),
                'reason' => '情節嚴重',
            ])->assertSessionHasNoErrors();

        $this->block->refresh();
        expect($this->block->blocked_until->toDateString())->toBe(today()->addDays(6)->toDateString())
            ->and($this->block->blocked_until->format('H:i'))->toBe('23:59')
            ->and($this->block->adjusted_by)->toBe($this->admin->id)
            ->and($this->block->adjust_reason)->toBe('情節嚴重');
    });

    it('也可以縮短', function () {
        $this->actingAs($this->admin)
            ->patch(route('admin.blocks.adjust', $this->block), [
                'until' => today()->toDateString(),
                'reason' => '通融',
            ])->assertSessionHasNoErrors();

        expect($this->block->fresh()->blocked_until->toDateString())->toBe(today()->toDateString());
    });

    it('不能改到過去，也一定要填原因', function () {
        $this->actingAs($this->admin)
            ->patch(route('admin.blocks.adjust', $this->block), [
                'until' => today()->subDay()->toDateString(), 'reason' => 'x',
            ])->assertSessionHasErrors('until');

        $this->actingAs($this->admin)
            ->patch(route('admin.blocks.adjust', $this->block), [
                'until' => today()->addDay()->toDateString(),
            ])->assertSessionHasErrors('reason');
    });

    it('已解封的就不能再改期限', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.blocks.lift', $this->block), ['reason' => '通融']);

        $this->actingAs($this->admin)
            ->patch(route('admin.blocks.adjust', $this->block), [
                'until' => today()->addDays(3)->toDateString(), 'reason' => '反悔',
            ]);

        expect($this->block->fresh()->adjusted_at)->toBeNull();
    });

    it('值班人員不能改期限', function () {
        $this->actingAs(User::factory()->staff()->create())
            ->patch(route('admin.blocks.adjust', $this->block), [
                'until' => today()->addDays(3)->toDateString(), 'reason' => 'x',
            ])->assertForbidden();
    });

    it('延長後封鎖仍然有效，申請還是送不出去', function () {
        $this->actingAs($this->admin)
            ->patch(route('admin.blocks.adjust', $this->block), [
                'until' => today()->addDays(6)->toDateString(), 'reason' => '情節嚴重',
            ]);

        $this->travelTo(today()->addDays(3));

        expect(BorrowBlock::active()->count())->toBe(1);
    });
});

describe('違規設定', function () {
    it('每一種違規都能各自設定上限與天數', function () {
        $payload = [];
        foreach (BorrowViolationKind::cases() as $kind) {
            $payload[$kind->value.'_limit'] = 3;
            $payload[$kind->value.'_block_days'] = 7;
        }

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.settings.section.update', 'penalty'), $payload)
            ->assertSessionHasNoErrors();

        foreach (BorrowViolationKind::cases() as $kind) {
            expect($kind->limit())->toBe(3)->and($kind->blockDays())->toBe(7);
        }
    });

    it('上限 0 代表只記錄不封鎖', function () {
        Setting::put(BorrowViolationKind::SelfCancel->settingKey('limit'), 0);

        $this->actingAs($this->borrower)
            ->post(route('my.borrows.cancel', pendingRequest()))
            ->assertSessionHasNoErrors();

        expect(BorrowViolation::count())->toBe(1)
            ->and(BorrowBlock::count())->toBe(0);
    });

    it('設定頁會帶出所有違規種類', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.settings.section.edit', 'penalty'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/Penalty')
                ->has('kinds', count(BorrowViolationKind::cases()))
                ->where('settings.self_cancel_limit', 2)
                ->where('settings.returned_late_limit', 0));
    });
});
