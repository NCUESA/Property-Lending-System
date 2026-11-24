<?php

use App\Enums\Role;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestOverview;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Illuminate\Support\Facades\DB;

const LEGACY_SQL = 'tests/Fixtures/legacy/dump.sql';
const LEGACY_CODERULE = 'tests/Fixtures/legacy/coderule.csv';

beforeEach(function () {
    $this->seed(LocationSeeder::class);
    $this->artisan('property:import-legacy', ['--sql' => LEGACY_SQL, '--coderule' => LEGACY_CODERULE])
        ->assertSuccessful();
});

function importLegacyBorrows(): void
{
    test()->artisan('borrow:import-legacy', ['--sql' => LEGACY_SQL])->assertSuccessful();
}

test('器材匯入會修正重複與錯誤的財產編號', function () {
    expect(Property::pluck('name', 'ssid')->all())->toEqualCanonicalizing([
        '31030001' => '個人電腦主機',
        'J0080004' => 'L型POP落地架',
        'J0080006' => '海報架',
        'J0081004' => '大型雙面展示板',
        'J0081005' => '大型雙面展示板',
    ]);
});

test('經手人依學號建立帳號，離職者為一般借用人且不停用', function () {
    importLegacyBorrows();

    expect(User::where('student_id', 'S1110001')->value('role'))->toBe(Role::Admin)
        ->and(User::where('student_id', 'S1110002')->value('role'))->toBe(Role::Staff);

    $left = User::where('student_id', 'S1110003')->sole();
    expect($left->role)->toBe(Role::Borrower)->and($left->is_active)->toBeTrue();
});

test('既有帳號的角色只升不降、姓名不覆寫', function () {
    $user = User::factory()->create(['student_id' => 's1110003', 'name' => 'SSO 姓名', 'role' => Role::Staff]);

    importLegacyBorrows();

    expect($user->fresh())->role->toBe(Role::Staff)->name->toBe('SSO 姓名')
        ->and(User::whereRaw('upper(student_id) = ?', ['S1110003'])->count())->toBe(1);
});

test('借用單沿用舊單號，經手人、時間與明細都對應到新欄位', function () {
    importLegacyBorrows();

    expect(BorrowRequest::find(1))->toBeNull(); // 測試單

    $request = BorrowRequest::with('items.property')->findOrFail(100);
    expect($request)
        ->lent_by->toBe(User::where('student_id', 'S1110001')->value('id'))
        ->returned_by->toBe(User::where('student_id', 'S1110002')->value('id'))
        ->deposit_box_no->toBe(5)
        ->id_card_taken->toBeTrue()
        ->deposit_returned->toBeTrue()
        ->and($request->lent_at->format('Y-m-d H:i'))->toBe('2025-03-02 00:00')
        ->and($request->returned_at->toDateString())->toBe('2025-03-06')
        ->and($request->created_at->format('Y-m-d H:i'))->toBe('2025-03-01 18:30')
        ->and($request->updated_at->format('Y-m-d H:i'))->toBe('2025-03-05 10:00'); // 舊 updated_at 是 UTC

    // 重複列的 J0081005 只留下已歸還那筆；明細上的 J0081004 是大型雙面展示板
    expect($request->items->mapWithKeys(fn ($i) => [$i->property->ssid => $i->status->value])->all())
        ->toEqual(['J0081004' => 'returned', 'J0081005' => 'returned'])
        ->and($request->items->firstWhere('property.ssid', 'J0081004')->property->name)->toBe('大型雙面展示板')
        ->and(BorrowRequestOverview::find(100)->status->value)->toBe('returned_late');
});

test('整張沒借出的單不寫出借人，處理人記在備註', function () {
    importLegacyBorrows();

    expect(BorrowRequest::findOrFail(101))
        ->lent_by->toBeNull()
        ->lent_at->toBeNull()
        ->deposit_box_no->toBeNull()
        ->remark->toBe("數量不足\n舊系統處理人：乙幹部")
        ->and(BorrowRequestOverview::find(101)->status->value)->toBe('cancelled');
});

test('對不到帳號的經手人保留原名，未歸還的單維持借出', function () {
    importLegacyBorrows();

    $request = BorrowRequest::with('items.property')->findOrFail(102);
    expect($request)
        ->lent_by->toBeNull()
        ->legacy_lender_name->toBe('外人')
        ->returned_at->toBeNull()
        ->and($request->items->sole()->property->ssid)->toBe('31030001')
        ->and(BorrowRequestOverview::find(102)->status->value)->toBe('lent');
});

test('可以重複執行，序號接在最大的舊單號之後', function () {
    importLegacyBorrows();
    importLegacyBorrows();

    expect(BorrowRequest::count())->toBe(3)
        ->and(DB::table('borrow_request_items')->count())->toBe(4)
        ->and(User::count())->toBe(3)
        ->and(DB::scalar("SELECT nextval('borrow_requests_id_seq')"))->toBe(103);
});

test('新系統已有借用單時拒絕執行', function () {
    BorrowRequest::factory()->create();

    $this->artisan('borrow:import-legacy', ['--sql' => LEGACY_SQL])
        ->expectsOutputToContain('新系統應已上線')
        ->assertFailed();
});

test('dry-run 不寫入資料庫', function () {
    $this->artisan('borrow:import-legacy', ['--sql' => LEGACY_SQL, '--dry-run' => true])->assertSuccessful();

    expect(BorrowRequest::count())->toBe(0)->and(User::count())->toBe(0);
});
