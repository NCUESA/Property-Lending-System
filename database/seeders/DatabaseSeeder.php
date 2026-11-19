<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyPool;
use App\Models\PropertySubcategory;
use App\Models\User;
use App\Models\UserGroup;
use App\Support\PropertyCode;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            LocationSeeder::class,
            SettingSeeder::class,
        ]);

        if (! app()->isLocal()) {
            return;
        }

        $this->seedDemo();
    }

    /** 本機開發用的假資料。 */
    private function seedDemo(): void
    {
        $admin = User::factory()->admin()->create(['student_id' => 'admin', 'name' => '測試管理員', 'email' => 'admin@example.com']);
        User::factory()->staff()->create(['student_id' => 'staff', 'name' => '測試值班', 'email' => 'staff@example.com']);
        $member = User::factory()->create(['student_id' => 'S1234567', 'name' => '測試社團幹部', 'email' => 'member@example.com']);

        $av = PropertyCategory::create(['code' => '200', 'name' => '音響設備']);
        $furniture = PropertyCategory::create(['code' => '300', 'name' => '家具']);
        $mic = PropertySubcategory::create(['property_category_id' => $av->id, 'code' => '0', 'name' => '麥克風']);
        $speaker = PropertySubcategory::create(['property_category_id' => $av->id, 'code' => '1', 'name' => '喇叭']);
        $chair = PropertySubcategory::create(['property_category_id' => $furniture->id, 'code' => '0', 'name' => '折疊椅']);

        $jinde = Location::where('code', 'jinde')->firstOrFail();
        $baosan = Location::where('code', 'baosan')->firstOrFail();
        $warehouse = Location::where('code', '307')->firstOrFail();

        $make = function (Location $location, PropertySubcategory $sub, string $name, array $attrs = []) {
            return Property::factory()->create([
                'ssid' => PropertyCode::next($location, $sub),
                'property_subcategory_id' => $sub->id,
                'location_id' => $location->id,
                'name' => $name,
                ...$attrs,
            ]);
        };

        foreach ([$jinde, $baosan] as $location) {
            foreach (range(1, 4) as $_) {
                $make($location, $mic, '動圈式麥克風');
            }
        }
        foreach (range(1, 2) as $_) {
            $make($warehouse, $speaker, '主動式喇叭', ['auth_level' => 1]);
        }

        $chairs = PropertyPool::create(['name' => '折疊椅', 'location_id' => $jinde->id]);
        foreach (range(1, 30) as $_) {
            $make($warehouse, $chair, '折疊椅', ['property_pool_id' => $chairs->id]);
        }

        $cadres = UserGroup::create(['name' => '全體幹部']);
        $club = UserGroup::create(['name' => '社團負責人']);
        $cadres->subgroups()->attach($club);
        $club->members()->attach($member);
        $cadres->members()->attach($admin);
        $cadres->grants()->create(['property_subcategory_id' => $speaker->id]);
    }
}
