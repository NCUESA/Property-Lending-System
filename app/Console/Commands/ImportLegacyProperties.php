<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertySubcategory;
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

    /**
     * 舊系統的財產編號打錯：類別碼 400 不在 coderule 的任何區間內（最大到 299）。
     * 34000001 與 41030001 是同型號的兩台個人電腦主機，後者的 103（個人電腦）才正確，
     * 且 coderule 的 103-0 正是「個人電腦主機」。
     */
    private const SSID_FIXES = [
        '34000001' => '31030001',
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

        $dump = file_get_contents($sqlPath);

        if ($dump === false) {
            $this->error("讀取失敗：{$sqlPath}");

            return self::FAILURE;
        }

        $rows = $this->parseInsertRows($dump, 'property');

        if ($rows === []) {
            $this->error('在 dump 裡找不到 property 資料表的 INSERT。');

            return self::FAILURE;
        }

        $this->line('舊系統 property：'.count($rows).' 筆');

        $properties = [];
        $fixed = [];

        foreach ($rows as $row) {
            $r = $this->toAssoc($row);
            $ssid = (string) $r['ssid'];

            if (isset(self::SSID_FIXES[$ssid])) {
                $fixed[] = [$ssid, self::SSID_FIXES[$ssid], (string) $r['name']];
                $ssid = self::SSID_FIXES[$ssid];
            }

            $r['ssid'] = $ssid;
            $properties[] = $r;
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
            $this->warn('財產編號修正（類別碼不在 coderule 區間內）：');

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

    /**
     * 解析 mysqldump 的 INSERT INTO `table` VALUES (...),(...); 取出每一列。
     *
     * @return list<list<string|null>>
     */
    private function parseInsertRows(string $dump, string $table): array
    {
        $prefix = "INSERT INTO `{$table}` VALUES ";
        $rows = [];

        foreach (explode("\n", $dump) as $line) {
            if (! str_starts_with($line, $prefix)) {
                continue;
            }

            foreach ($this->parseValues(rtrim(substr($line, strlen($prefix)), ";\r")) as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * mysqldump 的值字串：字串用單引號並以反斜線轉義，NULL 不加引號。
     *
     * @return list<list<string|null>>
     */
    private function parseValues(string $values): array
    {
        $rows = [];
        $row = [];
        $field = '';
        $quoted = false;
        $inString = false;
        $inRow = false;
        $length = strlen($values);

        for ($i = 0; $i < $length; $i++) {
            $char = $values[$i];

            if ($inString) {
                if ($char === '\\') {
                    $field .= $this->unescape($values[$i + 1] ?? '');
                    $i++;
                } elseif ($char === "'") {
                    $inString = false;
                } else {
                    $field .= $char;
                }

                continue;
            }

            if (! $inRow) {
                if ($char === '(') {
                    $inRow = true;
                    $row = [];
                    $field = '';
                    $quoted = false;
                }

                continue;
            }

            if ($char === "'") {
                $inString = true;
                $quoted = true;
            } elseif ($char === ',') {
                $row[] = $this->finishField($field, $quoted);
                $field = '';
                $quoted = false;
            } elseif ($char === ')') {
                $row[] = $this->finishField($field, $quoted);
                $rows[] = $row;
                $inRow = false;
            } else {
                $field .= $char;
            }
        }

        return $rows;
    }

    private function finishField(string $raw, bool $quoted): ?string
    {
        if ($quoted) {
            return $raw;
        }

        $trimmed = trim($raw);

        return strcasecmp($trimmed, 'NULL') === 0 ? null : $trimmed;
    }

    private function unescape(string $char): string
    {
        return match ($char) {
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            '0' => "\0",
            'b' => "\x08",
            'Z' => "\x1a",
            default => $char,
        };
    }

    /**
     * @param  list<string|null>  $row
     * @return array<string, string|null>
     */
    private function toAssoc(array $row): array
    {
        $assoc = [];

        foreach (self::COLUMNS as $i => $column) {
            $assoc[$column] = $row[$i] ?? null;
        }

        return $assoc;
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
