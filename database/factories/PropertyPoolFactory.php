<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\PropertyPool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyPool>
 */
class PropertyPoolFactory extends Factory
{
    private const NAMES = ['折疊椅', '塑膠椅', '長桌', '三角錐', '紅龍柱'];

    /**
     * 本批次已發出、但還沒寫進資料庫的名稱（count(n) 會先展開全部屬性才寫入）。
     *
     * @var list<string>
     */
    private array $issued = [];

    public function definition(): array
    {
        return [
            'name' => fn () => $this->unusedName(),
            'location_id' => Location::factory(),
            'auth_level' => 0,
        ];
    }

    /**
     * 取一個資料庫裡還沒用過的名稱。
     *
     * property_pools 有 (location_id, name) 的 unique 約束，而測試會用
     * create(['name' => '折疊椅']) 明確指定名稱——那些值沒有經過 faker，
     * unique() 不知道它們存在，只靠 unique() 會隨機撞上（名稱只有 5 個，機率不低）。
     * 名單用完就改用流水名稱，避免 unique() 耗盡時丟 OverflowException。
     */
    private function unusedName(): string
    {
        $available = array_values(array_diff(
            self::NAMES,
            PropertyPool::query()->pluck('name')->all(),
            $this->issued,
        ));

        $name = $available === []
            ? '品項'.fake()->unique()->numberBetween(1, 999_999)
            : fake()->randomElement($available);

        $this->issued[] = $name;

        return $name;
    }
}
