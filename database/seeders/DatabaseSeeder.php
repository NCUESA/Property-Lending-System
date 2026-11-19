<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Property;
use App\Models\User;
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

        User::factory()->admin()->create(['student_id' => 'admin', 'name' => '測試管理員', 'email' => 'admin@example.com']);
        User::factory()->create(['student_id' => 'staff', 'name' => '測試值班', 'email' => 'staff@example.com']);

        Location::all()->each(fn (Location $location) => Property::factory()
            ->count(8)
            ->create(['location_id' => $location->id]));
    }
}
