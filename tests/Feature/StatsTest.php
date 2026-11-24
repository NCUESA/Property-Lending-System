<?php

use App\Models\BorrowRequest;
use App\Models\BorrowRequestItem;
use App\Models\DepartmentAlias;
use App\Models\Property;
use App\Models\PropertyPool;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/** 建一張借用單，items 為 [property, 狀態] 或 [pool, 狀態, 數量] */
function statsBorrow(array $attributes, array $items): BorrowRequest
{
    $borrow = BorrowRequest::factory()->create($attributes);

    foreach ($items as [$target, $status, $quantity]) {
        BorrowRequestItem::factory()->create([
            'borrow_request_id' => $borrow->id,
            'property_id' => $target instanceof Property ? $target->id : null,
            'property_pool_id' => $target instanceof PropertyPool ? $target->id : null,
            'quantity' => $quantity ?? 1,
            'status' => $status,
            'returned_quantity' => $status === 'returned' ? ($quantity ?? 1) : 0,
            'returned_at' => $status === 'returned' ? $attributes['due_date'].' 00:00:00' : null,
        ]);
    }

    return $borrow;
}

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->mic = Property::factory()->create(['name' => '麥克風']);
    $this->table = Property::factory()->create(['name' => '摺疊桌']);
    $this->chairs = PropertyPool::factory()->create(['name' => '折疊椅']);
    Property::factory()->count(10)->create(['property_pool_id' => $this->chairs->id]);

    $day = ['borrow_date' => '2026-03-02', 'due_date' => '2026-03-03', 'lent_at' => '2026-03-02 10:00:00'];

    statsBorrow([...$day, 'borrower_department' => '熱舞社'], [[$this->mic, 'returned', null], [$this->chairs, 'returned', 5]]);
    statsBorrow([...$day, 'borrower_department' => ' 熱舞 ', 'borrower_name' => '王小明', 'phone' => '0911111111'], [[$this->mic, 'returned', null]]);
    // 晚還：歸還日 3/3，3/6 才還
    $late = statsBorrow([...$day, 'borrower_department' => '吉他社', 'borrower_name' => '李小華', 'phone' => '0922222222'], [[$this->table, 'returned', null]]);
    $late->items()->update(['returned_at' => '2026-03-06 00:00:00']);
    $late->forceFill(['returned_at' => '2026-03-06 00:00:00'])->save();
    // 退回的單不算
    statsBorrow([...$day, 'borrower_department' => '熱舞社', 'lent_at' => null], [[$this->table, 'cancelled', null]]);
});

test('熱門清單依借出次數排序，品項池以數量計，退回不算', function () {
    $this->get(route('admin.stats.popular', ['level' => 'property']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Stats/Popular')
            ->has('rows', 3)
            ->where('rows.0.label', '麥克風')
            ->where('rows.0.times', 2)
            ->where('rows.0.property_id', $this->mic->id)
            ->where('rows.1.label', '折疊椅')
            ->where('rows.1.kind', 'pool')
            ->where('rows.1.quantity', 5)
            ->where('rows.1.item_days', 10) // 5 件 × 2 天
            ->where('rows.2.label', '摺疊桌')
            ->where('rows.2.times', 1));
});

test('熱門清單可篩選借用日範圍', function () {
    $this->get(route('admin.stats.popular', ['from' => '2026-04-01']))
        ->assertInertia(fn (Assert $page) => $page->has('rows', 0));

    $this->get(route('admin.stats.popular', ['from' => '2026-04-01', 'to' => '2026-03-01']))
        ->assertSessionHasErrors('to');
});

test('常借單位依同義詞合併後統計', function () {
    $this->get(route('admin.stats.departments'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Stats/Departments')
            ->has('rows', 3));

    DepartmentAlias::create(['alias' => '熱舞', 'canonical' => '熱舞社']);

    $this->get(route('admin.stats.departments'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 2)
            ->where('rows.0.department', '熱舞社')
            ->where('rows.0.times', 2)
            ->where('rows.0.quantity', 7)
            ->where('rows.0.spellings', ['熱舞', '熱舞社'])
            ->where('rows.1.department', '吉他社')
            ->where('rows.1.late', 1));
});

test('逾期排行可依單位或個人', function () {
    $this->get(route('admin.stats.overdue'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Stats/Overdue')
            ->has('rows', 1)
            ->where('rows.0.name', '吉他社')
            ->where('rows.0.times', 1)
            ->where('rows.0.max_days', 3));

    $this->get(route('admin.stats.overdue', ['by' => 'person']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.name', '李小華')
            ->where('rows.0.phone', '0922222222')
            ->where('rows.0.department', '吉他社'));
});

test('熱力圖回傳每天的借用單數與件數', function () {
    $this->get(route('admin.stats.heatmap', ['year' => 2026]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Stats/Heatmap')
            ->where('year', 2026)
            ->where('days.2026-03-02', ['requests' => 3, 'items' => 8]));
});

test('把寫法拖進群組只移動那一種寫法', function () {
    DepartmentAlias::create(['alias' => '熱舞', 'canonical' => '熱舞社']);

    $this->post(route('admin.stats.aliases.store'), [
        'aliases' => ['熱舞社'],
        'canonical' => '酷電熱舞社',
    ])->assertSessionHasNoErrors();

    // 「熱舞」還在原本的群組，不會被一起帶走
    expect(DepartmentAlias::pluck('canonical', 'alias')->all())->toEqual([
        '熱舞' => '熱舞社',
        '熱舞社' => '酷電熱舞社',
    ]);

    $this->delete(route('admin.stats.aliases.destroy'), ['aliases' => ['熱舞']])
        ->assertSessionHasNoErrors();

    expect(DepartmentAlias::where('alias', '熱舞')->exists())->toBeFalse();
});

test('拖進和自己同名的群組時不建立多餘的對應', function () {
    DepartmentAlias::create(['alias' => '熱舞社', 'canonical' => '舞蹈']);

    $this->post(route('admin.stats.aliases.store'), [
        'aliases' => ['熱舞社'],
        'canonical' => '熱舞社',
    ])->assertSessionHasNoErrors();

    expect(DepartmentAlias::count())->toBe(0);
});

test('群組改名時，同名的寫法也留在群組裡', function () {
    // 群組「熱舞社」：熱舞 → 熱舞社，加上借用單上本來就寫「熱舞社」的
    DepartmentAlias::create(['alias' => '熱舞', 'canonical' => '熱舞社']);

    $this->patch(route('admin.stats.aliases.rename'), ['from' => '熱舞社', 'to' => '酷電熱舞社'])
        ->assertSessionHasNoErrors();

    expect(DepartmentAlias::pluck('canonical', 'alias')->all())->toEqual([
        '熱舞' => '酷電熱舞社',
        '熱舞社' => '酷電熱舞社',
    ]);

    $this->get(route('admin.stats.departments'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.department', '酷電熱舞社')
            ->where('rows.0.times', 2));
});

test('群組改成和成員同名時，取消那個成員的對應', function () {
    DepartmentAlias::create(['alias' => '熱舞', 'canonical' => '熱舞社']);
    DepartmentAlias::create(['alias' => '酷電熱舞', 'canonical' => '熱舞社']);

    $this->patch(route('admin.stats.aliases.rename'), ['from' => '熱舞社', 'to' => '熱舞'])
        ->assertSessionHasNoErrors();

    expect(DepartmentAlias::pluck('canonical', 'alias')->all())->toEqual([
        '熱舞社' => '熱舞',
        '酷電熱舞' => '熱舞',
    ]);
});

test('群組名稱不能只有符號', function () {
    $this->post(route('admin.stats.aliases.store'), ['aliases' => ['熱舞'], 'canonical' => '*'])
        ->assertSessionHasErrors('canonical');

    $this->patch(route('admin.stats.aliases.rename'), ['from' => '熱舞社', 'to' => '--'])
        ->assertSessionHasErrors('to');

    expect(DepartmentAlias::count())->toBe(0);
});

test('同義詞頁列出每一種寫法與目前的對應', function () {
    DepartmentAlias::create(['alias' => '熱舞', 'canonical' => '熱舞社']);

    $this->get(route('admin.stats.aliases.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Stats/Aliases')
            ->has('rows', 3)
            ->where('rows.0.spelling', '吉他社')
            ->where('rows.0.canonical', null));
});

test('值班人員看不到數據頁', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.stats.popular'))
        ->assertForbidden();
});
