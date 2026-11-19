<?php

use App\Enums\BorrowItemStatus;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestItem;
use App\Models\Location;
use App\Models\Property;
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

it('只允許一件器材有一筆進行中的借用', function (BorrowItemStatus $existing) {
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

it('同一張借用單不能重複加入同一件器材', function () {
    $item = BorrowRequestItem::factory()->create(['status' => BorrowItemStatus::Returned]);

    BorrowRequestItem::factory()->create([
        'borrow_request_id' => $item->borrow_request_id,
        'property_id' => $item->property_id,
        'status' => BorrowItemStatus::Returned,
    ]);
})->throws(UniqueConstraintViolationException::class);

it('資料庫拒絕不合法的狀態字串', function () {
    $item = BorrowRequestItem::factory()->create();

    DB::table('borrow_request_items')->where('id', $item->id)->update(['status' => '2']);
})->throws(QueryException::class);

it('資料庫拒絕歸還日早於借用日', function () {
    BorrowRequest::factory()->create(['borrow_date' => '2026-11-20', 'due_date' => '2026-11-19']);
})->throws(QueryException::class);

it('available 排除不開放借用與進行中的器材', function () {
    $free = Property::factory()->create(['location_id' => loc('jinde')->id]);
    Property::factory()->notLendable()->create(['location_id' => loc('jinde')->id]);
    $lent = Property::factory()->create(['location_id' => loc('jinde')->id]);
    BorrowRequestItem::factory()->create(['property_id' => $lent->id, 'status' => BorrowItemStatus::Lent]);
    $returned = Property::factory()->create(['location_id' => loc('jinde')->id]);
    BorrowRequestItem::factory()->create(['property_id' => $returned->id, 'status' => BorrowItemStatus::Returned]);

    expect(Property::available()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$free->id, $returned->id])->sort()->values()->all());
});

it('進德可借進德與 307，寶山只借寶山，405 不開放', function () {
    $ids = [];
    foreach (['jinde', 'baosan', '307', '405'] as $code) {
        $ids[$code] = Property::factory()->create(['location_id' => loc($code)->id])->id;
    }

    expect(Property::pickupableFrom(loc('jinde'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$ids['jinde'], $ids['307']])->sort()->values()->all())
        ->and(Property::pickupableFrom(loc('baosan'))->pluck('id')->all())
        ->toBe([$ids['baosan']])
        ->and(Location::pickupPoints()->pluck('code')->all())
        ->toBe(['jinde', 'baosan']);
});

it('有借用紀錄的器材不能被刪除', function () {
    $item = BorrowRequestItem::factory()->create(['status' => BorrowItemStatus::Returned]);

    $item->property->delete();
})->throws(QueryException::class);
