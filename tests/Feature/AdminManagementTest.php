<?php

use App\Enums\BorrowItemStatus;
use App\Http\Controllers\Admin\SettingController;
use App\Models\BorrowPeriod;
use App\Models\BorrowRequestItem;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyPool;
use App\Models\PropertySubcategory;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserGroup;
use App\Support\AuthLevel;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([LocationSeeder::class, SettingSeeder::class]);
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->jinde = Location::where('code', 'jinde')->firstOrFail();
    $this->category = PropertyCategory::factory()->create(['code' => '200', 'name' => '音響設備']);
    $this->sub = PropertySubcategory::factory()->create(['property_category_id' => $this->category->id, 'code' => '0', 'name' => '麥克風']);
});

function propertyPayload(array $overrides = []): array
{
    return [
        'ssid' => 'J2000001',
        'property_subcategory_id' => PropertySubcategory::value('id'),
        'name' => '動圈式麥克風',
        'location_id' => Location::where('code', 'jinde')->value('id'),
        'is_school_owned' => false,
        'auth_level' => 0,
        'is_lendable' => true,
        ...$overrides,
    ];
}

describe('財產編號 API', function () {
    it('回傳下一個編號（JSON）', function () {
        Property::factory()->create(['ssid' => 'J2000004', 'property_subcategory_id' => $this->sub->id]);

        $this->getJson(route('admin.property-codes.next', ['location_id' => $this->jinde->id, 'property_subcategory_id' => $this->sub->id]))
            ->assertOk()
            ->assertExactJson(['ssid' => 'J2000005', 'prefix' => 'J2000']);
    });

    it('參數錯誤回 422，不是 200 + success:false', function () {
        $this->getJson(route('admin.property-codes.next', ['location_id' => 999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['location_id', 'property_subcategory_id']);
    });

    it('流水號用完回 422 並說明原因', function () {
        Property::factory()->create(['ssid' => 'J2000999']);

        $this->getJson(route('admin.property-codes.next', ['location_id' => $this->jinde->id, 'property_subcategory_id' => $this->sub->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['property_subcategory_id']);
    });
});

describe('器材維護', function () {
    it('新增器材並上傳照片到 public 磁碟', function () {
        Storage::fake('public');

        $this->post(route('admin.properties.store'), propertyPayload(['image' => UploadedFile::fake()->image('mic.jpg')]))
            ->assertRedirect(route('admin.properties.index'))
            ->assertInertiaFlash('toast.type', 'success');

        $property = Property::sole();
        Storage::disk('public')->assertExists($property->image_path);
    });

    it('更換照片時刪除舊檔', function () {
        Storage::fake('public');
        $this->post(route('admin.properties.store'), propertyPayload(['image' => UploadedFile::fake()->image('a.jpg')]));
        $old = Property::sole()->image_path;

        $this->put(route('admin.properties.update', Property::sole()), propertyPayload(['image' => UploadedFile::fake()->image('b.jpg')]))
            ->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists(Property::sole()->image_path);
    });

    it('財產編號格式錯誤或重複時回傳中文錯誤', function () {
        Property::factory()->create(['ssid' => 'J2000001']);

        $this->post(route('admin.properties.store'), propertyPayload())
            ->assertSessionHasErrors(['ssid' => '財產編號 已經存在。']);
        $this->post(route('admin.properties.store'), propertyPayload(['ssid' => 'j-1']))
            ->assertSessionHasErrors('ssid');
    });

    it('有借用紀錄的器材不能刪除（舊系統回 success:true + 500）', function () {
        $item = BorrowRequestItem::factory()->create();

        $this->delete(route('admin.properties.destroy', $item->property))
            ->assertSessionHasErrors('property');

        expect(Property::whereKey($item->property_id)->exists())->toBeTrue();
    });

    it('不存在的器材回 404', function () {
        $this->delete(route('admin.properties.destroy', 99999))->assertNotFound();
    });

    it('列表可搜尋', function () {
        Property::factory()->create(['ssid' => 'J2000001', 'name' => '投影機']);
        Property::factory()->create(['ssid' => 'J2000002', 'name' => '延長線']);

        $this->get(route('admin.properties.index', ['q' => '投影']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Properties/Index')
                ->has('properties.data', 1)
                ->where('properties.data.0.name', '投影機'));
    });

    it('列表可篩選品項池，或只看不含大量借用品項的器材', function () {
        $pool = PropertyPool::factory()->create();
        Property::factory()->create(['ssid' => 'J2000001', 'property_pool_id' => $pool->id]);
        Property::factory()->create(['ssid' => 'J2000002']);

        $this->get(route('admin.properties.index', ['pool_id' => 'none']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('properties.data', 1)
                ->where('properties.data.0.ssid', 'J2000002'));

        $this->get(route('admin.properties.index', ['pool_id' => $pool->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('properties.data', 1)
                ->where('properties.data.0.ssid', 'J2000001'));

        $this->get(route('admin.properties.index', ['pool_id' => 'abc']))
            ->assertSessionHasErrors('pool_id');
    });

    it('列表預設每頁 10 筆', function () {
        Property::factory()->count(12)->create(['property_subcategory_id' => $this->sub->id]);

        $this->get(route('admin.properties.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('properties.per_page', 10)
                ->has('properties.data', 10)
                ->where('properties.total', 12)
                ->where('perPageOptions', [10, 30, 50, 100]));
    });

    it('可以自己選每頁筆數', function () {
        Property::factory()->count(12)->create(['property_subcategory_id' => $this->sub->id]);

        $this->get(route('admin.properties.index', ['per_page' => 50]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('properties.per_page', 50)
                ->has('properties.data', 12));
    });

    it('每頁筆數只接受給定的選項', function () {
        $this->get(route('admin.properties.index', ['per_page' => 25]))
            ->assertSessionHasErrors('per_page');
    });
});

describe('編碼規則清冊', function () {
    it('已有器材使用的類別不能改編號', function () {
        Property::factory()->create(['property_subcategory_id' => $this->sub->id]);

        $this->put(route('admin.subcategories.update', $this->sub), ['code' => '1', 'name' => '麥克風'])
            ->assertSessionHasErrors('code');
        $this->put(route('admin.categories.update', $this->category), ['code' => '201', 'name' => '音響'])
            ->assertSessionHasErrors('code');

        // 只改名稱可以
        $this->put(route('admin.subcategories.update', $this->sub), ['code' => '0', 'name' => '無線麥克風'])
            ->assertSessionHasNoErrors();
    });

    it('次要類別編號在同一主類內不能重複', function () {
        $this->post(route('admin.categories.subcategories.store', $this->category), ['code' => '0', 'name' => '重複'])
            ->assertSessionHasErrors('code');

        $other = PropertyCategory::factory()->create(['code' => '300']);
        $this->post(route('admin.categories.subcategories.store', $other), ['code' => '0', 'name' => '不同主類可以'])
            ->assertSessionHasNoErrors();
    });

    it('清冊預設每頁 10 個主要類別', function () {
        PropertyCategory::factory()->count(12)->create();

        $this->get(route('admin.categories.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Categories/Index')
                ->where('categories.per_page', 10)
                ->has('categories.data', 10)
                ->where('categories.total', 13) // beforeEach 的 200 也算
                ->where('perPageOptions', [10, 30, 50, 100]));
    });

    it('搜尋同時比對類別編號、類別名稱與次類名稱', function () {
        // beforeEach：200 音響設備 / 次類 0 麥克風
        $this->get(route('admin.categories.index', ['q' => '麥克風']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories.data', 1)
                ->where('categories.data.0.code', '200'));

        $this->get(route('admin.categories.index', ['q' => '音響']))
            ->assertInertia(fn (Assert $page) => $page->has('categories.data', 1));

        $this->get(route('admin.categories.index', ['q' => '200']))
            ->assertInertia(fn (Assert $page) => $page->has('categories.data', 1));

        $this->get(route('admin.categories.index', ['q' => '不存在的東西']))
            ->assertInertia(fn (Assert $page) => $page->has('categories.data', 0));
    });

    it('大類別按區間篩選，並帶出各區間的類別數', function () {
        PropertyCategory::factory()->create(['code' => '004']); // 001-050
        PropertyCategory::factory()->create(['code' => '103']); // 100-199

        $this->get(route('admin.categories.index', ['group' => '001-050']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories.data', 1)
                ->where('categories.data.0.code', '004')
                ->where('groups.0.key', '001-050')
                ->where('groups.0.count', 1)
                ->where('groups.3.key', '200-299')
                ->where('groups.3.count', 1));

        $this->get(route('admin.categories.index', ['group' => '100-199']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories.data', 1)
                ->where('categories.data.0.code', '103'));
    });

    it('大類別只接受已定義的區間', function () {
        $this->get(route('admin.categories.index', ['group' => '900-999']))
            ->assertSessionHasErrors('group');
    });
});

describe('人員與群組', function () {
    it('人員列表預設每頁 10 筆，可切換每頁筆數', function () {
        User::factory()->count(11)->create(); // 加上登入的管理員共 12 位

        $this->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->where('users.per_page', 10)
                ->has('users.data', 10)
                ->where('users.total', 12)
                ->where('perPageOptions', [10, 30, 50, 100]));

        $this->get(route('admin.users.index', ['per_page' => 30]))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 12));

        $this->get(route('admin.users.index', ['per_page' => 7]))
            ->assertSessionHasErrors('per_page');
    });

    it('管理員不能把自己降級或停用', function () {
        $this->put(route('admin.users.update', $this->admin), [
            'name' => $this->admin->name, 'student_id' => $this->admin->student_id, 'email' => $this->admin->email,
            'role' => 'staff', 'is_active' => false,
        ])->assertSessionHasErrors(['role', 'is_active']);
    });

    it('可以把借用人升級為值班人員', function () {
        $user = User::factory()->create();

        $this->put(route('admin.users.update', $user), [
            'name' => $user->name, 'student_id' => $user->student_id, 'email' => $user->email,
            'role' => 'staff', 'is_active' => true,
        ])->assertSessionHasNoErrors();

        expect($user->fresh()->isStaffOrAbove())->toBeTrue();
    });

    it('群組設定頁帶出等級與可選的等級清單', function () {
        $group = UserGroup::factory()->level(2)->create();

        $this->get(route('admin.groups.edit', $group))
            ->assertInertia(fn (Assert $page) => $page
                ->where('group.auth_level', 2)
                ->has('options.levels', AuthLevel::MAX + 1)
                ->where('options.levels.0.value', 0)
                ->where('options.levels.5.value', 5));

        $this->get(route('admin.groups.index'))
            ->assertInertia(fn (Assert $page) => $page->where('groups.0.auth_level', 2));
    });

    it('可以從後台改群組等級，改完立刻影響借得到什麼', function () {
        $sub = PropertySubcategory::factory()->create();
        $lv1 = Property::factory()->restricted(1)->create(['property_subcategory_id' => $sub->id]);
        $lv3 = Property::factory()->restricted(3)->create(['property_subcategory_id' => $sub->id]);

        $group = UserGroup::factory()->level(1)->create();
        $member = User::factory()->create();
        $group->members()->attach($member);
        $group->grants()->create(['property_subcategory_id' => $sub->id]);

        expect(Property::borrowableBy($member)->whereKey($lv3->id)->exists())->toBeFalse();

        $this->put(route('admin.groups.update', $group), [
            'name' => $group->name, 'auth_level' => 3,
        ])->assertSessionHasNoErrors();

        expect($group->fresh()->auth_level)->toBe(3)
            ->and(Property::borrowableBy($member)->whereKey($lv1->id)->exists())->toBeTrue()
            ->and(Property::borrowableBy($member)->whereKey($lv3->id)->exists())->toBeTrue();
    });

    it('等級超出範圍會被擋下來', function () {
        $group = UserGroup::factory()->create();

        $this->put(route('admin.groups.update', $group), [
            'name' => $group->name, 'auth_level' => AuthLevel::MAX + 1,
        ])->assertSessionHasErrors('auth_level');
    });

    it('器材表單會帶出等級選項', function () {
        $this->get(route('admin.properties.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('authLevels', AuthLevel::MAX + 1)
                ->where('authLevels.0.label', '公開（訪客可借）')
                ->where('authLevels.3.label', '等級 3'));

        $this->get(route('admin.pools.index'))
            ->assertInertia(fn (Assert $page) => $page->has('authLevels', AuthLevel::MAX + 1));
    });

    it('器材等級不能超過上限', function () {
        $location = Location::where('code', 'jinde')->firstOrFail();
        $sub = PropertySubcategory::factory()->create();

        $this->post(route('admin.properties.store'), [
            'ssid' => 'J9990001', 'property_subcategory_id' => $sub->id,
            'location_id' => $location->id, 'name' => '測試', 'is_school_owned' => true,
            'is_lendable' => true, 'auth_level' => AuthLevel::MAX + 1,
        ])->assertSessionHasErrors('auth_level');
    });

    it('一次同步群組的成員、子群組與授權', function () {
        $group = UserGroup::factory()->create();
        $child = UserGroup::factory()->create();
        $member = User::factory()->create();
        $restricted = Property::factory()->restricted()->create();

        $this->put(route('admin.groups.update', $group), [
            'name' => $group->name,
            'member_ids' => [$member->id],
            'subgroup_ids' => [$child->id],
            'grants' => ['property_ids' => [$restricted->id], 'category_ids' => [$this->category->id]],
        ])->assertSessionHasNoErrors();

        expect($group->members()->pluck('users.id')->all())->toBe([$member->id])
            ->and($group->subgroups()->pluck('user_groups.id')->all())->toBe([$child->id])
            ->and($group->grants()->count())->toBe(2)
            ->and(Property::borrowableBy($member)->whereKey($restricted->id)->exists())->toBeTrue();
    });

    it('群組循環回傳表單錯誤且不留下半套資料', function () {
        $parent = UserGroup::factory()->create();
        $child = UserGroup::factory()->create();
        $parent->subgroups()->attach($child);

        $this->put(route('admin.groups.update', $child), [
            'name' => '改了名字',
            'subgroup_ids' => [$parent->id],
        ])->assertSessionHasErrors('subgroup_ids');

        expect($child->fresh()->name)->not->toBe('改了名字');
    });

    it('群組不能包含自己', function () {
        $group = UserGroup::factory()->create();

        $this->put(route('admin.groups.update', $group), ['name' => $group->name, 'subgroup_ids' => [$group->id]])
            ->assertSessionHasErrors(['subgroup_ids.0' => '群組不能包含自己。']);
    });
});

describe('大量借用品項：挑選池內器材', function () {
    beforeEach(function () {
        // 進德可借出進德與 307 倉庫的器材；寶山只借寶山
        $this->pool = PropertyPool::factory()->create(['location_id' => $this->jinde->id, 'name' => '折疊椅']);
        $this->store307 = Location::where('code', '307')->firstOrFail();
        $this->baosan = Location::where('code', 'baosan')->firstOrFail();

        $this->atJinde = Property::factory()->create(['location_id' => $this->jinde->id, 'property_subcategory_id' => $this->sub->id]);
        $this->at307 = Property::factory()->create(['location_id' => $this->store307->id, 'property_subcategory_id' => $this->sub->id]);
        $this->atBaosan = Property::factory()->create(['location_id' => $this->baosan->id, 'property_subcategory_id' => $this->sub->id]);
    });

    it('候選清單只含該取用地點服務範圍內的器材', function () {
        $ids = collect($this->getJson(route('admin.pools.properties.index', $this->pool))
            ->assertOk()
            ->json('properties'))
            ->pluck('id');

        expect($ids)->toContain($this->atJinde->id, $this->at307->id)
            ->and($ids)->not->toContain($this->atBaosan->id);
    });

    it('可以一次把器材挑進品項，未勾選的移出', function () {
        $this->put(route('admin.pools.properties.update', $this->pool), [
            'property_ids' => [$this->atJinde->id, $this->at307->id],
        ])->assertSessionHasNoErrors();

        expect($this->atJinde->refresh()->property_pool_id)->toBe($this->pool->id)
            ->and($this->at307->refresh()->property_pool_id)->toBe($this->pool->id);

        // 再送一次只留一件，另一件要被移出
        $this->put(route('admin.pools.properties.update', $this->pool), [
            'property_ids' => [$this->atJinde->id],
        ])->assertSessionHasNoErrors();

        expect($this->atJinde->refresh()->property_pool_id)->toBe($this->pool->id)
            ->and($this->at307->refresh()->property_pool_id)->toBeNull();
    });

    it('拒絕不在出借範圍內的器材', function () {
        $this->put(route('admin.pools.properties.update', $this->pool), [
            'property_ids' => [$this->atBaosan->id],
        ])->assertSessionHasErrors('property_ids');

        expect($this->atBaosan->refresh()->property_pool_id)->toBeNull();
    });

    it('拒絕搶走已屬於其他品項的器材', function () {
        $other = PropertyPool::factory()->create(['location_id' => $this->jinde->id, 'name' => '長桌']);
        $this->atJinde->update(['property_pool_id' => $other->id]);

        $this->put(route('admin.pools.properties.update', $this->pool), [
            'property_ids' => [$this->atJinde->id],
        ])->assertSessionHasErrors('property_ids');

        expect($this->atJinde->refresh()->property_pool_id)->toBe($other->id);
    });

    it('不能把池縮到少於借出中未歸還的數量', function () {
        $this->put(route('admin.pools.properties.update', $this->pool), [
            'property_ids' => [$this->atJinde->id, $this->at307->id],
        ])->assertSessionHasNoErrors();

        BorrowRequestItem::factory()->fromPool($this->pool, 2)->create(['status' => BorrowItemStatus::Lent]);

        $this->put(route('admin.pools.properties.update', $this->pool), [
            'property_ids' => [$this->atJinde->id],
        ])->assertSessionHasErrors('property_ids');

        expect($this->at307->refresh()->property_pool_id)->toBe($this->pool->id);
    });
});

describe('設定與借用期間', function () {
    it('期間重疊回傳表單錯誤', function () {
        BorrowPeriod::create(['name' => '寒假', 'starts_on' => '2027-01-20', 'ends_on' => '2027-02-15', 'is_open' => false]);

        $this->post(route('admin.periods.store'), ['name' => '重疊', 'starts_on' => '2027-02-01', 'ends_on' => '2027-02-20', 'is_open' => true])
            ->assertSessionHasErrors(['starts_on' => '這段期間和既有的期間重疊了。']);
    });

    it('借用規則分頁更新押金與天數', function () {
        $this->put(route('admin.settings.section.update', 'borrow'), [
            'deposit_amount' => 200, 'max_days' => 3, 'open_mode' => 'always_open', 'allow_advance' => false, 'deposit_box_count' => 20, 'fixed_duration' => true,
        ])->assertSessionHasNoErrors();

        expect(Setting::get('borrow.deposit_amount'))->toBe(200)
            ->and(Setting::get('borrow.max_days'))->toBe(3);
    });

    it('注意事項分頁更新條款', function () {
        $this->put(route('admin.settings.section.update', 'notices'), ['notices' => ['只有一條']])
            ->assertSessionHasNoErrors();

        expect(Setting::get('borrow.notices'))->toBe(['只有一條']);
    });

    it('儲存一個分頁不會動到其他分頁的設定', function () {
        $this->seed(SettingSeeder::class);

        $this->put(route('admin.settings.section.update', 'notices'), ['notices' => ['改過了']])
            ->assertSessionHasNoErrors();

        // 注意事項存檔後，借用規則要原封不動
        expect(Setting::get('borrow.notices'))->toBe(['改過了'])
            ->and(Setting::get('borrow.deposit_amount'))->toBe(100)
            ->and(Setting::get('borrow.max_days'))->toBe(7);

        $this->put(route('admin.settings.section.update', 'borrow'), [
            'deposit_amount' => 500, 'max_days' => 14, 'open_mode' => 'periods', 'allow_advance' => true, 'deposit_box_count' => 20, 'fixed_duration' => true,
        ])->assertSessionHasNoErrors();

        expect(Setting::get('borrow.deposit_amount'))->toBe(500)
            ->and(Setting::get('borrow.notices'))->toBe(['改過了']);
    });

    it('設定分頁只載入自己的欄位，也只驗證自己的欄位', function () {
        $this->get(route('admin.settings.section.edit', 'notices'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/Notices')
                ->has('settings.notices')
                ->missing('settings.deposit_amount'));

        // 在注意事項分頁不必送借用規則的欄位
        $this->put(route('admin.settings.section.update', 'notices'), ['notices' => []])
            ->assertSessionHasNoErrors();
    });

    it('側邊欄的系統群組來自後端分頁清單，且只有管理員看得到', function () {
        // 比對整份清單，新增分頁時不會因為順序變動就壞掉
        $this->get(route('admin.settings.section.edit', 'borrow'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('settingsSections.0.title', '借用規則')
                ->where(
                    'settingsSections',
                    fn ($sections) => collect($sections)->pluck('slug')->all() === SettingController::slugs(),
                ));

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('admin.borrows.index'))
            ->assertInertia(fn (Assert $page) => $page->where('settingsSections', []));
    });

    describe('開放方式', function () {
        it('借用規則分頁會帶出三種模式，值勤時間標成尚未開放', function () {
            $this->get(route('admin.settings.section.edit', 'borrow'))
                ->assertInertia(fn (Assert $page) => $page
                    ->has('openModes', 3)
                    ->where('openModes.0.value', 'always_open')
                    ->where('openModes.1.value', 'periods')
                    ->where('openModes.2.value', 'duty_hours')
                    ->where('openModes.2.available', false)
                    ->where('openModes.0.available', true));
        });

        it('不接受還沒實作的值勤時間模式', function () {
            $this->put(route('admin.settings.section.update', 'borrow'), [
                'deposit_amount' => 100, 'max_days' => 7, 'open_mode' => 'duty_hours',
                'allow_advance' => false, 'deposit_box_count' => 20, 'fixed_duration' => true,
            ])->assertSessionHasErrors('open_mode');

            $this->put(route('admin.settings.section.update', 'borrow'), [
                'deposit_amount' => 100, 'max_days' => 7, 'open_mode' => 'nope',
                'allow_advance' => false, 'deposit_box_count' => 20, 'fixed_duration' => true,
            ])->assertSessionHasErrors('open_mode');
        });

        it('借用期間頁在 always_open 時標示不生效', function () {
            Setting::put('borrow.open_mode', 'always_open');

            $this->get(route('admin.periods.index'))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('effective', false)
                    ->where('openMode.label', '預設開放借用'));

            Setting::put('borrow.open_mode', 'periods');

            $this->get(route('admin.periods.index'))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('effective', true)
                    ->where('openMode.label', '設定內時間開放借用'));
        });

        it('切成 always_open 之後，停借期間就真的不生效了', function () {
            BorrowPeriod::create([
                'name' => '寒假', 'starts_on' => today()->subDay(),
                'ends_on' => today()->addDays(10), 'is_open' => false,
            ]);

            Setting::put('borrow.open_mode', 'periods');
            expect(BorrowPeriod::isOpenOn(today()))->toBeFalse();

            Setting::put('borrow.open_mode', 'always_open');
            expect(BorrowPeriod::isOpenOn(today()))->toBeTrue();
        });
    });

    it('未定義的設定分頁回 404', function () {
        $this->get('/admin/settings/nope')->assertNotFound();
    });

    it('settings 導向第一個分頁', function () {
        $this->get(route('admin.settings.edit'))
            ->assertRedirect('/admin/settings/borrow');
    });
});
