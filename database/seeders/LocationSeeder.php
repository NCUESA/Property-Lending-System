<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

/**
 * 正式資料，可重跑。
 * 規則沿用舊系統：選「進德」可借進德與 307 倉庫的器材，選「寶山」只借寶山，405 不開放借用。
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $jinde = Location::updateOrCreate(['code' => 'jinde'], [
            'ssid_prefix' => 'J', 'name' => '進德', 'is_pickup_point' => true, 'pickup_location_id' => null, 'sort_order' => 1,
        ]);

        Location::updateOrCreate(['code' => 'baosan'], [
            'ssid_prefix' => 'B', 'name' => '寶山', 'is_pickup_point' => true, 'pickup_location_id' => null, 'sort_order' => 2,
        ]);

        Location::updateOrCreate(['code' => '307'], [
            'ssid_prefix' => '3', 'name' => '307倉庫', 'is_pickup_point' => false, 'pickup_location_id' => $jinde->id, 'sort_order' => 3,
        ]);

        Location::updateOrCreate(['code' => '405'], [
            'ssid_prefix' => '4', 'name' => '405議會辦公室', 'is_pickup_point' => false, 'pickup_location_id' => null, 'sort_order' => 4,
        ]);
    }
}
