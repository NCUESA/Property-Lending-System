<?php

namespace Database\Factories;

use App\Models\PropertyCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * @extends Factory<PropertyCategory>
 */
class PropertyCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            // 用 closure 延後取號：count(n) 批次建立時，後面幾筆才看得到前面已佔用的編號
            'code' => fn () => $this->unusedCode(),
            'name' => fake()->randomElement(['什項用具', '資訊設備', '音響設備', '家具']),
        ];
    }

    /**
     * 取一個資料庫裡還沒人用的 3 位數編號。
     *
     * 不能只靠 fake()->unique()：測試會用 create(['code' => '200']) 明確指定編號
     * （'200' 是財產編號 J2000001 的一部分，屬於被測行為的輸入），這些值沒有經過
     * faker，unique() 不知道它們存在，於是會隨機撞上 code 的 unique constraint，
     * 造成與受測程式無關的偶發失敗。和 LocationFactory 避開 seeder 的 J/B/3/4 同類。
     */
    private function unusedCode(): string
    {
        $all = array_map(
            fn (int $n) => str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            range(0, 999),
        );

        $available = array_values(
            array_diff($all, PropertyCategory::query()->pluck('code')->all()),
        );

        if ($available === []) {
            throw new RuntimeException('property_categories 的 3 位數編號已全部用完，無法再產生測試資料。');
        }

        return fake()->randomElement($available);
    }
}
