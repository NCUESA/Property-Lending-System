<?php

use App\Models\BorrowRequest;
use App\Models\BorrowRequestItem;
use App\Models\BorrowViolation;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyPool;
use App\Models\User;
use App\Models\UserGroup;
use Database\Seeders\DemoSeeder;
use Database\Seeders\LocationSeeder;

const PURGE_SQL = 'tests/Fixtures/legacy/dump.sql';

beforeEach(function () {
    $this->seed([LocationSeeder::class, DemoSeeder::class]);
    $this->artisan('property:import-legacy', [
        '--sql' => PURGE_SQL,
        '--coderule' => 'tests/Fixtures/legacy/coderule.csv',
    ])->assertSuccessful();
});

function purgeDemo(): void
{
    test()->artisan('demo:purge', ['--sql' => PURGE_SQL, '--force' => true])->assertSuccessful();
}

test('刪除示範帳號、器材、品項池、群組與類別，保留舊系統資料', function () {
    $real = User::factory()->staff()->create(['student_id' => 'S1110001']);

    expect(Property::count())->toBe(45);

    purgeDemo();

    expect(Property::pluck('ssid')->sort()->values()->all())
        ->toBe(['31030001', 'J0080004', 'J0080006', 'J0081004', 'J0081005'])
        ->and(User::pluck('id')->all())->toBe([$real->id])
        ->and(PropertyPool::count())->toBe(0)
        ->and(UserGroup::count())->toBe(0)
        ->and(PropertyCategory::pluck('code')->sort()->values()->all())->toBe(['008', '103']);
});

test('用到示範器材或示範帳號的借用單一起刪除，其他借用單保留', function () {
    $demoMic = Property::where('name', '動圈式麥克風')->first();
    $demoItem = BorrowRequestItem::factory()->create(['property_id' => $demoMic->id]);
    BorrowViolation::forceCreate([
        'kind' => 'overdue',
        'phone' => '0900000000',
        'borrower_name' => '測試',
        'borrow_request_id' => $demoItem->borrow_request_id,
        'occurred_at' => now(),
    ]);

    $realItem = BorrowRequestItem::factory()->create([
        'property_id' => Property::where('ssid', 'J0081005')->value('id'),
    ]);
    $byDemoStaff = BorrowRequest::factory()->create([
        'lent_by' => User::where('student_id', 'staff')->value('id'),
    ]);

    purgeDemo();

    expect(BorrowRequest::pluck('id')->all())->toBe([$realItem->borrow_request_id])
        ->and(BorrowRequest::find($byDemoStaff->id))->toBeNull()
        ->and(BorrowViolation::count())->toBe(0);
});

test('編號與舊系統相同的器材視為真實器材，不刪除', function () {
    // 示範資料先建了 J0081005，舊資料匯入時覆寫成真實器材
    Property::where('ssid', 'J0081005')->update(['name' => '動圈式麥克風']);

    purgeDemo();

    expect(Property::where('ssid', 'J0081005')->exists())->toBeTrue();
});

test('群組加了真實成員、類別被改名時保留', function () {
    $real = User::factory()->create();
    UserGroup::where('name', '全體幹部')->sole()->members()->attach($real);
    PropertyCategory::where('code', '300')->update(['name' => '家具類']);

    purgeDemo();

    expect(UserGroup::pluck('name')->all())->toBe(['全體幹部'])
        ->and(PropertyCategory::where('code', '300')->exists())->toBeTrue();
});

test('沒確認就不刪除', function () {
    $this->artisan('demo:purge', ['--sql' => PURGE_SQL])
        ->expectsConfirmation('確定要刪除以上資料？', 'no')
        ->assertSuccessful();

    expect(Property::count())->toBe(45)->and(User::count())->toBe(3);
});

test('找不到 dump 時拒絕執行', function () {
    $this->artisan('demo:purge', ['--sql' => 'missing.sql', '--force' => true])->assertFailed();

    expect(Property::count())->toBe(45);
});
