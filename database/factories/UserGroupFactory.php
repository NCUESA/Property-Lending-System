<?php

namespace Database\Factories;

use App\Models\UserGroup;
use App\Support\AuthLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserGroup>
 */
class UserGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            // 預設給最高級，這樣多數測試只要關心「授權範圍」就好
            'auth_level' => AuthLevel::MAX,
        ];
    }

    /** 指定群組的授權等級。 */
    public function level(int $level): static
    {
        return $this->state(fn () => ['auth_level' => $level]);
    }
}
