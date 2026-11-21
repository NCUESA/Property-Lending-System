<?php

use App\Enums\BorrowItemStatus;
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
});

describe('人員與群組', function () {
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

    it('更新押金、借用天數與注意事項', function () {
        $this->put(route('admin.settings.update'), [
            'deposit_amount' => 200, 'max_days' => 3, 'default_open' => true, 'allow_advance' => false,
            'notices' => ['只有一條'],
        ])->assertSessionHasNoErrors();

        expect(Setting::get('borrow.deposit_amount'))->toBe(200)
            ->and(Setting::get('borrow.notices'))->toBe(['只有一條']);
    });
});
