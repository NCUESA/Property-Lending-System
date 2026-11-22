<?php

use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyPool;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([LocationSeeder::class, SettingSeeder::class]);
    $this->jinde = Location::where('code', 'jinde')->firstOrFail();
    $this->baosan = Location::where('code', 'baosan')->firstOrFail();
    $this->warehouse = Location::where('code', '307')->firstOrFail();
    $this->admin = User::factory()->admin()->create();
});

describe('互連關係（有向圖）', function () {
    it('seeder 的進德→307 關係搬進了 location_links', function () {
        expect($this->jinde->servedLocationIds())
            ->toEqualCanonicalizing([$this->jinde->id, $this->warehouse->id])
            ->and($this->baosan->servedLocationIds())->toBe([$this->baosan->id]);
    });

    it('同一個倉庫可以同時掛在多個取用地點底下', function () {
        $this->baosan->storageLocations()->attach($this->warehouse->id);

        expect($this->warehouse->fresh()->pickupLocations->pluck('id')->all())
            ->toEqualCanonicalizing([$this->jinde->id, $this->baosan->id]);
    });

    it('借用頁依照圖撈器材，不是寫死的', function () {
        $inWarehouse = Property::factory()->create(['location_id' => $this->warehouse->id]);

        // 一開始只有進德借得到 307
        $this->get(route('borrow.create', ['location' => 'baosan']))
            ->assertInertia(fn (Assert $page) => $page->has('properties', 0));

        $this->baosan->storageLocations()->attach($this->warehouse->id);

        $this->get(route('borrow.create', ['location' => 'baosan']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('properties', 1)
                ->where('properties.0.id', $inWarehouse->id));
    });

    it('移除關係後就借不到了', function () {
        Property::factory()->create(['location_id' => $this->warehouse->id]);

        $this->jinde->storageLocations()->detach($this->warehouse->id);

        $this->get(route('borrow.create', ['location' => 'jinde']))
            ->assertInertia(fn (Assert $page) => $page->has('properties', 0));
    });

    it('只看直接相連的一層，不會沿著圖一路傳遞', function () {
        $deep = Location::factory()->create(['is_pickup_point' => false]);
        $this->warehouse->storageLocations()->attach($deep->id);
        Property::factory()->create(['location_id' => $deep->id]);

        // 進德→307→deep，但進德借不到 deep
        expect($this->jinde->servedLocationIds())->not->toContain($deep->id);
    });

    it('停用的存放地點不列入可借範圍', function () {
        Property::factory()->create(['location_id' => $this->warehouse->id]);
        $this->warehouse->update(['is_active' => false]);

        expect($this->jinde->fresh()->servedLocationIds())->toBe([$this->jinde->id]);
    });

    it('資料庫擋掉自己連自己', function () {
        expect(fn () => $this->jinde->storageLocations()->attach($this->jinde->id))
            ->toThrow(QueryException::class);
    });

    it('同一條邊不會重複', function () {
        $this->jinde->storageLocations()->syncWithoutDetaching([$this->warehouse->id]);

        expect(DB::table('location_links')->count())->toBe(1);
    });
});

describe('地點管理', function () {
    it('列表帶出器材數與是否可刪', function () {
        Property::factory()->create(['location_id' => $this->jinde->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.locations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Locations/Index')
                ->has('locations', 4)
                ->where('locations.0.name', '進德')
                ->where('locations.0.properties_count', 1)
                ->where('locations.0.prefix_locked', true)
                ->where('locations.0.deletable', false)
                ->where('locations.0.serves', [$this->warehouse->id]));
    });

    it('可以新增地點', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.locations.store'), [
                'code' => 'annex', 'name' => '新倉庫', 'ssid_prefix' => 'X',
                'is_pickup_point' => false, 'is_active' => true, 'sort_order' => 9,
            ])->assertSessionHasNoErrors();

        expect(Location::where('code', 'annex')->exists())->toBeTrue();
    });

    it('代碼與字首不能重複', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.locations.store'), [
                'code' => 'jinde', 'name' => '重複', 'ssid_prefix' => 'X',
                'is_pickup_point' => true, 'is_active' => true, 'sort_order' => 9,
            ])->assertSessionHasErrors('code');

        $this->actingAs($this->admin)
            ->post(route('admin.locations.store'), [
                'code' => 'other', 'name' => '重複', 'ssid_prefix' => 'J',
                'is_pickup_point' => true, 'is_active' => true, 'sort_order' => 9,
            ])->assertSessionHasErrors('ssid_prefix');
    });

    it('字首只能是一個大寫英數字', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.locations.store'), [
                'code' => 'other', 'name' => '小寫', 'ssid_prefix' => 'x',
                'is_pickup_point' => true, 'is_active' => true, 'sort_order' => 9,
            ])->assertSessionHasErrors('ssid_prefix');
    });

    it('已有器材的地點不能改字首', function () {
        Property::factory()->create(['location_id' => $this->jinde->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.locations.update', $this->jinde), [
                'code' => 'jinde', 'name' => '進德', 'ssid_prefix' => 'Z',
                'is_pickup_point' => true, 'is_active' => true, 'sort_order' => 1,
            ])->assertSessionHasErrors('ssid_prefix');

        expect($this->jinde->fresh()->ssid_prefix)->toBe('J');
    });

    it('停用的取用地點不出現在借用頁', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.locations.update', $this->baosan), [
                'code' => 'baosan', 'name' => '寶山', 'ssid_prefix' => 'B',
                'is_pickup_point' => true, 'is_active' => false, 'sort_order' => 2,
            ])->assertSessionHasNoErrors();

        $this->get(route('borrow.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('locations', 1)
                ->where('locations.0.code', 'jinde'));
    });

    it('改成非取用地點時會清掉它連出去的邊', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.locations.update', $this->jinde), [
                'code' => 'jinde', 'name' => '進德', 'ssid_prefix' => 'J',
                'is_pickup_point' => false, 'is_active' => true, 'sort_order' => 1,
            ])->assertSessionHasNoErrors();

        expect($this->jinde->fresh()->storageLocations)->toHaveCount(0);
    });

    it('有器材或品項的地點不能刪除', function () {
        Property::factory()->create(['location_id' => $this->jinde->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.locations.destroy', $this->jinde))
            ->assertSessionHasErrors('id');

        $withPool = Location::factory()->create();
        PropertyPool::factory()->create(['location_id' => $withPool->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.locations.destroy', $withPool))
            ->assertSessionHasErrors('id');

        expect(Location::whereKey($this->jinde->id)->exists())->toBeTrue();
    });

    it('沒有器材的地點可以刪除，邊也跟著消失', function () {
        $this->actingAs($this->admin)
            ->delete(route('admin.locations.destroy', $this->warehouse))
            ->assertSessionHasNoErrors();

        expect(Location::whereKey($this->warehouse->id)->exists())->toBeFalse()
            ->and(DB::table('location_links')->count())->toBe(0);
    });
});

describe('互連關係設定', function () {
    it('可以整批設定一個取用地點借得到哪些地方', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.locations.links.update', $this->baosan), [
                'storage_location_ids' => [$this->warehouse->id],
            ])->assertSessionHasNoErrors();

        expect($this->baosan->fresh()->servedLocationIds())
            ->toEqualCanonicalizing([$this->baosan->id, $this->warehouse->id]);
    });

    it('送空陣列等於清空', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.locations.links.update', $this->jinde), [
                'storage_location_ids' => [],
            ])->assertSessionHasNoErrors();

        expect($this->jinde->fresh()->servedLocationIds())->toBe([$this->jinde->id]);
    });

    it('自己會被自動濾掉，不會撞到資料庫的 CHECK', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.locations.links.update', $this->jinde), [
                'storage_location_ids' => [$this->jinde->id, $this->warehouse->id],
            ])->assertSessionHasNoErrors();

        expect($this->jinde->fresh()->storageLocations->pluck('id')->all())
            ->toBe([$this->warehouse->id]);
    });

    it('非取用地點不能設定互連', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.locations.links.update', $this->warehouse), [
                'storage_location_ids' => [$this->baosan->id],
            ])->assertSessionHasErrors('storage_location_ids');
    });

    it('值班人員不能管理地點', function () {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('admin.locations.index'))->assertForbidden();
        $this->actingAs($staff)
            ->put(route('admin.locations.links.update', $this->jinde), ['storage_location_ids' => []])
            ->assertForbidden();
    });
});
