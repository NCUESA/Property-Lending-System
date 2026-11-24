<?php

namespace Database\Seeders;

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

        $this->call(DemoSeeder::class);
    }
}
