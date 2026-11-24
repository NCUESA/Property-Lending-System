<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertySubcategory;
use App\Support\Legacy\LegacyPropertyCodes;
use App\Support\Legacy\MysqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 從 coderule.csv（編碼規則）與舊系統 sa__property 的 MySQL dump 匯入類別與器材清冊。
 *
 * 類別與子類別的名稱一律以 coderule.csv 為準；coderule 沒有細分子類別的類別，
 * 子類別名稱沿用類別名稱（見 BARE_SUBCATEGORY_NAME 的說明）。
 */
class ImportLegacyProperties extends Command
{
    protected $signature = 'property:import-legacy
        {--sql=sqlexample : 舊系統 MySQL dump 的路徑}
        {--coderule=coderule.csv : 編碼規則 CSV 的路徑}
        {--fresh : 匯入前清空 properties 與類別清冊}
        {--dry-run : 只輸出報告，不寫入資料庫}';

    protected $description = '匯入 coderule.csv 的編碼規則與舊系統的器材清冊';

    /**
     * 舊系統 belong_place（實際存放地點）→ locations.code。
     * 財產編號第 1 碼也代表地點，但兩者在舊資料裡有 6 筆不一致；
     * 編號屬於財產本身不應變動，所以存放地點一律取 belong_place。
     */
    private const LOCATION_MAP = [
        '進德' => 'jinde',
        '寶山' => 'baosan',
        '307' => '307',
        '405' => '405',
    ];

    /** 舊 property 資料表的欄位順序（mysqldump 不帶欄位名稱）。 */
    private const COLUMNS = [
        'id', 'ssid', 'class', 'name', 'second_name', 'order_number', 'price',
        'department', 'depositary', 'belong_place', 'get_day', 'format', 'remark',
        'enable_lending', 'lending_status', 'property_status', 'img_url',
        'school_property', 'created_at', 'updated_at',
    ];

    public function handle(): int
    {
        $sqlPath = base_path((string) $this->option('sql'));
        $codePath = base_path((string) $this->option('coderule'));

        foreach ([$sqlPath, $codePath] as $path) {
            if (! is_readable($path)) {
                $this->error("讀不到檔案：{$path}");

                return self::FAILURE;
            }
        }

        [$categories, $subcategories] = $this->parseCoderule($codePath);
        $this->line('coderule：'.count($categories).' 個類別、'.array_sum(array_map('count', $subcategories)).' 個子類別');

        $rows = MysqlDump::open($sqlPath)->rows('property', self::COLUMNS);

        if ($rows === []) {
            $this->error('在 dump 裡找不到 property 資料表的 INSERT。');

            return self::FAILURE;
        }

        $this->line('舊系統 property：'.count($rows).' 筆');

        $properties = [];
        $fixed = [];

        foreach ($rows as $r) {
            $ssid = LegacyPropertyCodes::forProperty((string) $r['id'], (string) $r['ssid']);

            if ($ssid !== $r['ssid']) {
                $fixed[] = [(string) $r['ssid'], $ssid, (string) $r['name']];
            }

            $r['ssid'] = $ssid;
            $properties[] = $r;
        }

        $counts = array_count_values(array_map(fn (array $r): string => (string) $r['ssid'], $properties));

        if ($duplicates = array_keys(array_filter($counts, fn (int $n): bool => $n > 1))) {
            // updateOrCreate 會把重複的編號合併成一筆，器材會無聲無息地少掉
            $this->error('修正後仍有重複的財產編號，請在 LegacyPropertyCodes 補上修正：'.implode('、', $duplicates));

            return self::FAILURE;
        }

        // 補上 coderule 沒有細分、但舊資料有用到的子類別
        $added = $this->fillMissingSubcategories($properties, $categories, $subcategories);

        if ($unknown = $this->unknownCategories($properties, $categories)) {
            $this->error('下列類別碼在 coderule.csv 裡沒有定義，無法決定名稱：'.implode('、', $unknown));

            return self::FAILURE;
        }

        $this->report($fixed, $added, $properties);

        if ($this->option('dry-run')) {
            $this->warn('--dry-run：未寫入資料庫。');

            return self::SUCCESS;
        }

        return $this->write($categories, $subcategories, $properties);
    }

    /**
     * @param  array<string, string>  $categories
     * @param  array<string, array<string, string>>  $subcategories
     * @param  list<array<string, string|null>>  $properties
     */
    private function write(array $categories, array $subcategories, array $properties): int
    {
        $locations = Location::query()->pluck('id', 'code');

        foreach (self::LOCATION_MAP as $place => $code) {
            if (! isset($locations[$code])) {
                $this->error("locations 裡找不到 code = {$code}（對應舊資料的「{$place}」），請先執行 LocationSeeder。");

                return self::FAILURE;
            }
        }

        if ($this->option('fresh')) {
            $borrowed = DB::table('borrow_request_items')->count();

            if ($borrowed > 0) {
                $this->error("borrow_request_items 還有 {$borrowed} 筆資料參照 properties，--fresh 會破壞借用紀錄，已中止。");

                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($categories, $subcategories, $properties, $locations) {
            if ($this->option('fresh')) {
                Property::query()->delete();
                PropertySubcategory::query()->delete();
                PropertyCategory::query()->delete();
            }

            $categoryIds = [];

            foreach ($categories as $code => $name) {
                $categoryIds[$code] = PropertyCategory::query()
                    ->updateOrCreate(['code' => $code], ['name' => $name])->id;
            }

            $subcategoryIds = [];

            foreach ($subcategories as $categoryCode => $subs) {
                foreach ($subs as $code => $name) {
                    $subcategoryIds[$categoryCode.$code] = PropertySubcategory::query()
                        ->updateOrCreate(
                            ['property_category_id' => $categoryIds[$categoryCode], 'code' => $code],
                            ['name' => $name],
                        )->id;
                }
            }

            foreach ($properties as $r) {
                $ssid = (string) $r['ssid'];
                $key = substr($ssid, 1, 3).substr($ssid, 4, 1);

                Property::query()->updateOrCreate(['ssid' => $ssid], [
                    'property_subcategory_id' => $subcategoryIds[$key],
                    'sequence_no' => $this->int($r['order_number']),
                    'name' => (string) $r['name'],
                    'second_name' => $this->nullIfBlank($r['second_name']),
                    'spec' => $this->nullIfBlank($r['format']),
                    'price' => $this->int($r['price']),
                    'department' => $this->nullIfBlank($r['department']),
                    'custodian' => $this->nullIfBlank($r['depositary']),
                    'location_id' => $locations[self::LOCATION_MAP[(string) $r['belong_place']]],
                    // bulk 品項池是新系統才有的概念，舊資料沒有對應欄位，留空由後台自行設定
                    'property_pool_id' => null,
                    'acquired_on' => $this->nullIfBlank($r['get_day']),
                    'is_school_owned' => $r['school_property'] === '1',
                    // 舊系統的權限是依 IP（authip 表），新系統改用群組授權，一律從 0 開始
                    'auth_level' => 0,
                    'image_path' => $this->nullIfBlank($r['img_url']),
                    'is_lendable' => $r['enable_lending'] === '1',
                    'remark' => $this->nullIfBlank($r['remark']),
                ]);
            }
        });

        $this->newLine();
        $this->info('匯入完成：'
            .PropertyCategory::query()->count().' 個類別、'
            .PropertySubcategory::query()->count().' 個子類別、'
            .Property::query()->count().' 筆器材。');

        return self::SUCCESS;
    }

    /**
     * 解析 coderule.csv。子類別的續列不會重複填「財產編碼號」，所以要沿用上一個類別碼。
     *
     * @return array{array<string, string>, array<string, array<string, string>>}
     */
    private function parseCoderule(string $path): array
    {
        $categories = [];
        $subcategories = [];
        $current = null;

        $fh = fopen($path, 'r');

        if ($fh === false) {
            throw new RuntimeException("無法開啟編碼規則檔：{$path}");
        }

        // 去掉 UTF-8 BOM，否則第一欄會多出不可見字元
        if (fread($fh, 3) !== "\xEF\xBB\xBF") {
            rewind($fh);
        }

        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if (count($row) < 5) {
                continue;
            }

            $code = trim((string) $row[1]);

            if (preg_match('/^\d{1,3}$/', $code) === 1) {
                $current = str_pad($code, 3, '0', STR_PAD_LEFT);
                $name = trim((string) $row[2]);

                if ($name !== '') {
                    $categories[$current] = $name;
                }
            }

            if ($current === null) {
                continue;
            }

            $subCode = trim((string) $row[3]);
            $subName = trim((string) $row[4]);

            if ($subCode !== '' && $subName !== '' && preg_match('/^[0-9A-Z]$/', $subCode) === 1) {
                $subcategories[$current][$subCode] = $subName;
            }
        }

        fclose($fh);
        ksort($categories);

        return [$categories, $subcategories];
    }

    /**
     * coderule 只列了類別、沒有細分子類別時（例如 204 HDMI線），
     * 子類別名稱沿用類別名稱，才能滿足 property_subcategories.name 的 NOT NULL。
     *
     * @param  list<array<string, string|null>>  $properties
     * @param  array<string, string>  $categories
     * @param  array<string, array<string, string>>  $subcategories
     * @return list<array{string, string, string}>
     */
    private function fillMissingSubcategories(array $properties, array $categories, array &$subcategories): array
    {
        $added = [];

        foreach ($properties as $r) {
            $ssid = (string) $r['ssid'];
            $cat = substr($ssid, 1, 3);
            $sub = substr($ssid, 4, 1);

            if (isset($subcategories[$cat][$sub]) || ! isset($categories[$cat])) {
                continue;
            }

            $subcategories[$cat][$sub] = $categories[$cat];
            $added[] = [$cat, $sub, $categories[$cat]];
        }

        return $added;
    }

    /**
     * @param  list<array<string, string|null>>  $properties
     * @param  array<string, string>  $categories
     * @return list<string>
     */
    private function unknownCategories(array $properties, array $categories): array
    {
        $unknown = [];

        foreach ($properties as $r) {
            $cat = substr((string) $r['ssid'], 1, 3);

            if (! isset($categories[$cat])) {
                $unknown[$cat] = true;
            }
        }

        return array_keys($unknown);
    }

    /**
     * @param  list<array{string, string, string}>  $fixed
     * @param  list<array{string, string, string}>  $added
     * @param  list<array<string, string|null>>  $properties
     */
    private function report(array $fixed, array $added, array $properties): void
    {
        if ($fixed !== []) {
            $this->newLine();
            $this->warn('財產編號修正（見 LegacyPropertyCodes）：');

            foreach ($fixed as [$from, $to, $name]) {
                $this->line("  {$from} → {$to}  {$name}");
            }
        }

        if ($added !== []) {
            $this->newLine();
            $this->warn('coderule 未細分子類別，沿用類別名稱：');

            foreach ($added as [$cat, $sub, $name]) {
                $this->line("  {$cat}-{$sub}  {$name}");
            }
        }

        $prefixes = Location::query()->pluck('ssid_prefix', 'code');
        $mismatched = [];

        foreach ($properties as $r) {
            $place = (string) $r['belong_place'];
            $expected = $prefixes[self::LOCATION_MAP[$place] ?? ''] ?? null;

            if ($expected !== null && $expected !== substr((string) $r['ssid'], 0, 1)) {
                $mismatched[] = [(string) $r['ssid'], $place];
            }
        }

        if ($mismatched !== []) {
            $this->newLine();
            $this->warn('編號地點與實際存放地點不一致（保留編號，location 取實際地點）：');

            foreach ($mismatched as [$ssid, $place]) {
                $this->line("  {$ssid}  實際存放：{$place}");
            }
        }
    }

    private function nullIfBlank(?string $value): ?string
    {
        return ($value === null || trim($value) === '') ? null : $value;
    }

    private function int(?string $value): ?int
    {
        return ($value === null || trim($value) === '') ? null : (int) $value;
    }
}
