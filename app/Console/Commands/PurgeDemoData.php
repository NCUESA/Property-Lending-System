<?php

namespace App\Console\Commands;

use App\Models\BorrowRequest;
use App\Models\BorrowViolation;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyPool;
use App\Models\PropertySubcategory;
use App\Models\User;
use App\Models\UserGroup;
use App\Support\Legacy\LegacyPropertyCodes;
use App\Support\Legacy\MysqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 刪除 DemoSeeder 建立的示範資料，以及用示範器材、示範帳號測試出來的借用單。
 *
 * 示範器材的編號（例如 J2000001）可能和舊系統真實器材重複，匯入時已被真實資料覆寫，
 * 所以器材一律以舊系統 dump 為準：編號在 dump 裡的視為真實器材，絕不刪除。
 * 其餘的條件都同時比對名稱與內容，被改過名、加進真實成員的資料會保留。
 */
class PurgeDemoData extends Command
{
    protected $signature = 'demo:purge
        {--sql=sqlexample : 舊系統 MySQL dump，用來辨識真實器材}
        {--force : 不詢問，直接刪除}';

    protected $description = '刪除 DemoSeeder 建立的示範資料';

    /** student_id → email，兩個都符合才算示範帳號 */
    private const USERS = [
        'admin' => 'admin@example.com',
        'staff' => 'staff@example.com',
        'S1234567' => 'member@example.com',
    ];

    private const PROPERTY_NAMES = ['動圈式麥克風', '主動式喇叭', '折疊椅'];

    private const POOL_NAME = '折疊椅';

    private const GROUP_NAMES = ['全體幹部', '社團負責人'];

    /** 類別碼 → [類別名稱, [子類別碼 → 子類別名稱]]；名稱被 coderule 匯入改掉的就是真實類別 */
    private const CATEGORIES = [
        '200' => ['音響設備', ['0' => '麥克風', '1' => '喇叭']],
        '300' => ['家具', ['0' => '折疊椅']],
    ];

    public function handle(): int
    {
        try {
            $legacySsids = $this->legacySsids();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            $this->line('需要舊系統 dump 才能分辨真實器材，請用 --sql 指定路徑。');

            return self::FAILURE;
        }

        $users = $this->demoUsers();
        $properties = Property::query()
            ->whereIn('name', self::PROPERTY_NAMES)
            ->whereNotIn('ssid', $legacySsids)
            ->pluck('id');
        $pools = PropertyPool::query()
            ->where('name', self::POOL_NAME)
            ->whereDoesntHave('properties', fn ($q) => $q->whereNotIn('id', $properties))
            ->pluck('id');
        $requests = $this->demoRequests($users, $properties, $pools);
        $groups = UserGroup::query()
            ->whereIn('name', self::GROUP_NAMES)
            ->whereDoesntHave('members', fn ($q) => $q->whereNotIn('users.id', $users))
            ->pluck('id');
        [$categories, $subcategories] = $this->demoCategories($properties);

        $this->table(['項目', '數量'], [
            ['示範帳號', $users->count()],
            ['示範器材', $properties->count()],
            ['品項池', $pools->count()],
            ['借用單（用到示範器材或示範帳號）', $requests->count()],
            ['群組', $groups->count()],
            ['類別／子類別', "{$categories->count()} / {$subcategories->count()}"],
        ]);

        if ($requests->isNotEmpty()) {
            $this->line('會刪除的借用單：#'.$requests->implode('、#'));
        }

        $total = $users->count() + $properties->count() + $pools->count() + $requests->count()
            + $groups->count() + $categories->count() + $subcategories->count();

        if ($total === 0) {
            $this->info('沒有示範資料。');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('確定要刪除以上資料？', false)) {
            $this->warn('已取消，未刪除任何資料。');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($users, $properties, $pools, $requests, $groups, $categories, $subcategories) {
            // 違規紀錄的 borrow_request_id 是 nullOnDelete，不先刪會留下沒有來源的違規
            BorrowViolation::query()->whereIn('borrow_request_id', $requests)->delete();
            BorrowRequest::query()->whereIn('id', $requests)->delete();

            UserGroup::query()->whereIn('id', $groups)->delete();
            Property::query()->whereIn('id', $properties)->delete();
            PropertyPool::query()->whereIn('id', $pools)->delete();
            PropertySubcategory::query()->whereIn('id', $subcategories)->delete();
            PropertyCategory::query()->whereIn('id', $categories)->delete();
            User::query()->whereIn('id', $users)->delete();
        });

        $this->info('示範資料已刪除。');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function legacySsids(): array
    {
        $rows = MysqlDump::open(base_path((string) $this->option('sql')))
            ->rows('property', ImportLegacyProperties::COLUMNS);

        if ($rows === []) {
            throw new RuntimeException('dump 裡找不到 property 資料表。');
        }

        return array_map(
            fn (array $r): string => LegacyPropertyCodes::forProperty((string) $r['id'], (string) $r['ssid']),
            $rows,
        );
    }

    /** @return Collection<int, int> */
    private function demoUsers(): Collection
    {
        return User::query()
            ->where(function ($q) {
                foreach (self::USERS as $studentId => $email) {
                    $q->orWhere(fn ($w) => $w->where('student_id', $studentId)->where('email', $email));
                }
            })
            ->pluck('id');
    }

    /**
     * @param  Collection<int, int>  $users
     * @param  Collection<int, int>  $properties
     * @param  Collection<int, int>  $pools
     * @return Collection<int, int>
     */
    private function demoRequests(Collection $users, Collection $properties, Collection $pools): Collection
    {
        $byItems = DB::table('borrow_request_items')
            ->whereIn('property_id', $properties)
            ->orWhereIn('property_pool_id', $pools)
            ->orWhereIn('returned_by', $users)
            ->pluck('borrow_request_id');

        $byUsers = BorrowRequest::query()
            ->whereIn('user_id', $users)
            ->orWhereIn('lent_by', $users)
            ->orWhereIn('returned_by', $users)
            ->orWhereIn('extended_by', $users)
            ->pluck('id');

        return $byItems->merge($byUsers)->map(fn ($id) => (int) $id)->unique()->sort()->values();
    }

    /**
     * 名稱沒被改過、刪掉示範器材後也沒有其他器材的類別與子類別。
     *
     * @param  Collection<int, int>  $properties
     * @return array{Collection<int, int>, Collection<int, int>}
     */
    private function demoCategories(Collection $properties): array
    {
        $categories = collect();
        $subcategories = collect();

        foreach (self::CATEGORIES as $code => [$name, $subs]) {
            $category = PropertyCategory::query()->where('code', $code)->where('name', $name)->first();

            if ($category === null) {
                continue;
            }

            $remaining = PropertySubcategory::query()->where('property_category_id', $category->id)->get();

            foreach ($remaining as $sub) {
                $isDemo = ($subs[$sub->code] ?? null) === $sub->name
                    && ! Property::query()->where('property_subcategory_id', $sub->id)->whereNotIn('id', $properties)->exists();

                if ($isDemo) {
                    $subcategories->push($sub->id);
                }
            }

            // 底下所有子類別都要刪時，類別才一起刪
            if ($remaining->pluck('id')->diff($subcategories)->isEmpty()) {
                $categories->push($category->id);
            }
        }

        return [$categories, $subcategories];
    }
}
