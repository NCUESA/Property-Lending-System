<?php

namespace App\Support;

use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyPool;
use App\Models\PropertySubcategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 器材清冊的 CSV 匯出與批量匯入。匯出的檔案可以直接改完再匯入。
 *
 * 匯入規則：
 * - 以「財產編號」對應：已存在就更新，不存在就新增；留空會依地點與子類別自動編號。
 * - 檔案裡有的欄位才會更新，空白代表清空；沒有的欄位（整欄刪掉）保留原值。
 * - 「類別」「子類別」「目前狀態」只供閱讀，匯入時不使用（類別以類別碼＋子類別碼為準）。
 */
final class PropertyCsv
{
    /** 欄位名稱 → 屬性；null 表示只匯出、不匯入 */
    public const COLUMNS = [
        '財產編號' => 'ssid',
        '名稱' => 'name',
        '別名' => 'second_name',
        '類別碼' => 'category_code',
        '類別' => null,
        '子類別碼' => 'subcategory_code',
        '子類別' => null,
        '規格' => 'spec',
        '價格' => 'price',
        '保管單位' => 'department',
        '保管人' => 'custodian',
        '存放地點' => 'location',
        '大量借用品項' => 'pool',
        '取得日期' => 'acquired_on',
        '校產' => 'is_school_owned',
        '開放借用' => 'is_lendable',
        '權限等級' => 'auth_level',
        '備註' => 'remark',
        '目前狀態' => null,
    ];

    public const MAX_ROWS = 2000;

    private const TRUE_VALUES = ['是', 'y', 'yes', 'true', '1', 'v', '✓'];

    private const FALSE_VALUES = ['否', 'n', 'no', 'false', '0', ''];

    /** @param  Builder<Property>  $query */
    public static function export(Builder $query, string $filename): StreamedResponse
    {
        $query->with(['subcategory.category:id,code,name', 'location:id,name', 'pool:id,name', 'activeBorrowItem:id,property_id,status', 'activeUnit:id,property_id'])
            ->orderBy('ssid');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // BOM：Excel 才會用 UTF-8 開啟，中文不會變亂碼
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_keys(self::COLUMNS), escape: '');

            foreach ($query->lazy(500) as $p) {
                /** @var Property $p */
                fputcsv($out, [
                    $p->ssid,
                    $p->name,
                    $p->second_name,
                    $p->subcategory->category->code,
                    $p->subcategory->category->name,
                    $p->subcategory->code,
                    $p->subcategory->name,
                    $p->spec,
                    $p->price,
                    $p->department,
                    $p->custodian,
                    $p->location->name,
                    $p->pool?->name,
                    $p->acquired_on?->toDateString(),
                    $p->is_school_owned ? '是' : '否',
                    $p->is_lendable ? '是' : '否',
                    $p->auth_level,
                    $p->remark,
                    $p->activeBorrowItem?->status->label() ?? ($p->activeUnit !== null ? '已借出' : '在庫'),
                ], escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * 解析並檢查每一列，不寫入資料庫。
     *
     * @return list<array{line: int, action: 'create'|'update'|'error', ssid: ?string, name: ?string, errors: list<string>, changes: list<string>, attributes: array<string, mixed>}>
     */
    public static function preview(string $path): array
    {
        [$header, $rows] = self::read($path);

        $unknown = array_diff($header, array_keys(self::COLUMNS));

        if ($unknown !== []) {
            throw new RuntimeException('看不懂這些欄位：'.implode('、', $unknown).'。請用匯出的檔案當範本。');
        }

        if (! in_array('財產編號', $header, true)) {
            throw new RuntimeException('缺少「財產編號」欄（新器材可以留空，但欄位要在）。');
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new RuntimeException('一次最多匯入 '.self::MAX_ROWS.' 列。');
        }

        $lookups = self::lookups();
        $existing = Property::query()->whereIn('ssid', array_filter(array_map(
            fn (array $r) => strtoupper(trim($r['財產編號'] ?? '')),
            $rows,
        )))->get()->keyBy('ssid');

        $results = [];
        $seen = [];
        $nextSerial = []; // 同一個檔案裡自動編號的流水號，依前綴累加

        foreach ($rows as $i => $row) {
            $line = $i + 2; // 第 1 列是標題
            $results[] = self::checkRow($line, $row, $existing, $lookups, $seen, $nextSerial);
        }

        return $results;
    }

    /**
     * 套用 preview 的結果；有任何錯誤列就整批不寫入。
     *
     * @param  list<array<string, mixed>>  $rows  preview() 的結果（從快取取回）
     * @return array{created: int, updated: int}
     */
    public static function apply(array $rows): array
    {
        if (collect($rows)->contains('action', 'error')) {
            throw new RuntimeException('還有錯誤的列，請修正後重新上傳。');
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as $row) {
                $ssid = is_string($row['ssid'] ?? null) ? $row['ssid'] : null;
                $attributes = is_array($row['attributes'] ?? null) ? $row['attributes'] : [];

                if ($ssid === null) {
                    throw new RuntimeException('預覽資料不完整，請重新上傳。');
                }

                if (($row['action'] ?? null) === 'update') {
                    Property::query()->where('ssid', $ssid)->firstOrFail()->update($attributes);
                    $updated++;
                } else {
                    Property::query()->create(['ssid' => $ssid, ...$attributes]);
                    $created++;
                }
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * @return array{list<string>, list<array<string, string>>}
     */
    private static function read(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false || trim($content) === '') {
            throw new RuntimeException('檔案是空的。');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        // Excel 在繁中 Windows 存成「CSV」時是 Big5，不是 UTF-8
        if (! mb_check_encoding($content, 'UTF-8')) {
            $converted = @mb_convert_encoding($content, 'UTF-8', 'CP950');

            if (! mb_check_encoding($converted, 'UTF-8')) {
                throw new RuntimeException('無法辨識檔案編碼，請存成「CSV UTF-8」。');
            }

            $content = $converted;
        }

        // 用 stream 解析，欄位內有換行（例如備註）也能正確讀取
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('無法讀取檔案。');
        }

        fwrite($stream, $content);
        rewind($stream);
        $lines = [];

        while (($cells = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            if ($cells === [null]) {
                continue; // 空白列
            }

            $lines[] = array_map(fn ($c) => trim((string) $c), $cells);
        }

        fclose($stream);

        $header = array_shift($lines) ?? [];
        $header = array_values(array_filter($header, fn ($h) => $h !== ''));

        $rows = [];

        foreach ($lines as $cells) {
            if (implode('', $cells) === '') {
                continue;
            }

            $rows[] = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
        }

        return [$header, $rows];
    }

    /**
     * @return array{subcategories: Collection<array-key, PropertySubcategory>, locations: Collection<array-key, Location>, pools: Collection<array-key, \Illuminate\Database\Eloquent\Collection<int, PropertyPool>>}
     */
    private static function lookups(): array
    {
        $locations = Location::query()->get();

        return [
            'subcategories' => PropertySubcategory::query()->with('category:id,code')->get()
                ->keyBy(fn (PropertySubcategory $s) => $s->category->code.'-'.$s->code),
            // 地點可以填代碼或名稱
            'locations' => $locations->keyBy('code')->union($locations->keyBy('name')),
            'pools' => PropertyPool::query()->get()->groupBy('name'),
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @param  Collection<string, Property>  $existing
     * @param  array{subcategories: Collection<array-key, PropertySubcategory>, locations: Collection<array-key, Location>, pools: Collection<array-key, \Illuminate\Database\Eloquent\Collection<int, PropertyPool>>}  $lookups
     * @param  array<string, int>  $seen
     * @param  array<string, int>  $nextSerial
     * @return array{line: int, action: 'create'|'update'|'error', ssid: ?string, name: ?string, errors: list<string>, changes: list<string>, attributes: array<string, mixed>}
     */
    private static function checkRow(int $line, array $row, Collection $existing, array $lookups, array &$seen, array &$nextSerial): array
    {
        $has = fn (string $column) => array_key_exists($column, $row);
        $value = fn (string $column) => $has($column) && $row[$column] !== '' ? $row[$column] : null;

        $errors = [];
        $ssid = $value('財產編號') !== null ? strtoupper((string) $value('財產編號')) : null;
        $property = $ssid !== null ? $existing->get($ssid) : null;
        $attributes = [];

        if ($ssid !== null) {
            if (isset($seen[$ssid])) {
                $errors[] = "財產編號 {$ssid} 和第 {$seen[$ssid]} 列重複";
            }

            $seen[$ssid] = $line;
        }

        // 類別：兩欄都在才處理；新增時一定要有
        if (($has('類別碼') || $has('子類別碼')) && ($value('類別碼') === null || $value('子類別碼') === null)) {
            if ($property !== null) {
                $errors[] = '「類別碼」「子類別碼」不能空白';
            }
        } elseif ($has('類別碼') || $has('子類別碼')) {
            $key = str_pad((string) $value('類別碼'), 3, '0', STR_PAD_LEFT).'-'.strtoupper((string) $value('子類別碼'));
            $sub = $lookups['subcategories']->get($key);

            if ($sub === null) {
                $errors[] = "找不到類別碼＋子類別碼 {$key}，請先到編碼規則新增";
            } else {
                $attributes['property_subcategory_id'] = $sub->id;
            }
        }

        if ($has('存放地點') && $value('存放地點') === null) {
            if ($property !== null) {
                $errors[] = '「存放地點」不能空白';
            }
        } elseif ($has('存放地點')) {
            $location = $lookups['locations']->get((string) $value('存放地點'));

            if ($location === null) {
                $errors[] = "找不到存放地點「{$value('存放地點')}」";
            } else {
                $attributes['location_id'] = $location->id;
            }
        }

        if ($has('大量借用品項')) {
            $poolName = $value('大量借用品項');
            $matches = $poolName !== null ? $lookups['pools']->get($poolName) : null;

            if ($poolName === null) {
                $attributes['property_pool_id'] = null;
            } elseif ($matches === null) {
                $errors[] = "找不到大量借用品項「{$poolName}」";
            } elseif ($matches->count() > 1) {
                $errors[] = "有 {$matches->count()} 個大量借用品項都叫「{$poolName}」，請先改成不同名稱";
            } else {
                $attributes['property_pool_id'] = $matches->first()?->id;
            }
        }

        foreach (['名稱' => 'name', '別名' => 'second_name', '規格' => 'spec', '保管單位' => 'department', '保管人' => 'custodian', '備註' => 'remark', '取得日期' => 'acquired_on'] as $column => $attribute) {
            if ($has($column)) {
                $attributes[$attribute] = $value($column);
            }
        }

        if (isset($attributes['acquired_on'])) {
            $attributes['acquired_on'] = str_replace('/', '-', (string) $attributes['acquired_on']);
        }

        if ($has('價格')) {
            $price = $value('價格');
            $attributes['price'] = $price === null ? null : str_replace([',', '$', '元', ' '], '', (string) $price);
        }

        if ($has('權限等級')) {
            $attributes['auth_level'] = $value('權限等級') ?? 0;
        }

        foreach (['校產' => 'is_school_owned', '開放借用' => 'is_lendable'] as $column => $attribute) {
            if (! $has($column)) {
                continue;
            }

            $raw = mb_strtolower((string) $row[$column]);

            if (in_array($raw, self::TRUE_VALUES, true)) {
                $attributes[$attribute] = true;
            } elseif (in_array($raw, self::FALSE_VALUES, true)) {
                $attributes[$attribute] = false;
            } else {
                $errors[] = "「{$column}」請填 是 或 否";
            }
        }

        // 新增時的預設值與必填
        if ($property === null) {
            $attributes += ['is_school_owned' => false, 'is_lendable' => true, 'auth_level' => 0];

            // 欄位沒填才報；填了但對不到的，上面已經有更具體的錯誤
            foreach (['名稱' => ['名稱'], '類別碼、子類別碼' => ['類別碼', '子類別碼'], '存放地點' => ['存放地點']] as $label => $columns) {
                if (collect($columns)->contains(fn (string $c) => $value($c) === null)) {
                    $errors[] = "新器材必須填「{$label}」";
                }
            }
        }

        $validator = Validator::make($attributes, [
            // 新增時的必填已在上面檢查過，這裡只管更新時不能清空
            'name' => $property === null ? ['nullable', 'string', 'max:100'] : ['sometimes', 'required', 'string', 'max:100'],
            'second_name' => ['nullable', 'string', 'max:100'],
            'spec' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'integer', 'min:0'],
            'department' => ['nullable', 'string', 'max:50'],
            'custodian' => ['nullable', 'string', 'max:50'],
            'acquired_on' => ['nullable', 'date_format:Y-m-d'],
            'auth_level' => ['integer', 'min:0', 'max:'.AuthLevel::MAX],
            'remark' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'name' => '名稱', 'second_name' => '別名', 'spec' => '規格', 'price' => '價格',
            'department' => '保管單位', 'custodian' => '保管人', 'acquired_on' => '取得日期',
            'auth_level' => '權限等級', 'remark' => '備註',
        ]);

        $errors = array_values([...$errors, ...$validator->errors()->all()]);

        if ($ssid !== null && preg_match('/^[0-9A-Z][0-9]{3}[0-9A-Z][0-9]{3}$/', $ssid) !== 1) {
            $errors[] = "財產編號 {$ssid} 格式不對（8 碼：地點 1 碼＋類別 3 碼＋子類別 1 碼＋流水號 3 碼）";
        }

        // 沒填編號的新器材：依地點與子類別自動編號（同一個檔案裡接著往下編）
        if ($errors === [] && $ssid === null) {
            $location = Location::query()->find($attributes['location_id'] ?? null);
            $sub = PropertySubcategory::query()->with('category')->find($attributes['property_subcategory_id'] ?? null);

            if ($location?->ssid_prefix === null || $sub === null) {
                $errors[] = '這個存放地點沒有編號代碼，無法自動編號，請自行填財產編號';
            } else {
                $prefix = PropertyCode::prefix($location, $sub);

                try {
                    $serial = $nextSerial[$prefix] ?? (int) substr(PropertyCode::next($location, $sub), 5);
                } catch (RuntimeException $e) {
                    $serial = 1000;
                }

                if ($serial > 999) {
                    $errors[] = "財產編號 {$prefix} 的流水號已用完";
                } else {
                    $ssid = $prefix.str_pad((string) $serial, 3, '0', STR_PAD_LEFT);
                    $nextSerial[$prefix] = $serial + 1;
                    $attributes['sequence_no'] = $serial;
                }
            }
        }

        $changes = $property !== null ? self::diff($property, $attributes) : [];

        return [
            'line' => $line,
            'action' => $errors !== [] ? 'error' : ($property !== null ? 'update' : 'create'),
            'ssid' => $ssid,
            'name' => $attributes['name'] ?? $property?->name,
            'errors' => $errors,
            'changes' => $changes,
            'attributes' => $validator->errors()->isEmpty() ? self::cast($attributes) : $attributes,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function cast(array $attributes): array
    {
        foreach (['price', 'auth_level'] as $key) {
            if (isset($attributes[$key])) {
                $attributes[$key] = (int) $attributes[$key];
            }
        }

        return $attributes;
    }

    /**
     * 更新時列出哪些欄位會變，讓匯入前能先確認。
     *
     * @param  array<string, mixed>  $attributes
     * @return list<string>
     */
    private static function diff(Property $property, array $attributes): array
    {
        $labels = [
            'name' => '名稱', 'second_name' => '別名', 'property_subcategory_id' => '類別',
            'spec' => '規格', 'price' => '價格', 'department' => '保管單位', 'custodian' => '保管人',
            'location_id' => '存放地點', 'property_pool_id' => '大量借用品項', 'acquired_on' => '取得日期',
            'is_school_owned' => '校產', 'is_lendable' => '開放借用', 'auth_level' => '權限等級', 'remark' => '備註',
        ];

        $changes = [];

        foreach (self::cast($attributes) as $key => $new) {
            $old = $property->getAttribute($key);
            $old = $old instanceof \DateTimeInterface ? $old->format('Y-m-d') : $old;

            if ((string) $old !== (string) $new && isset($labels[$key])) {
                $changes[] = $labels[$key];
            }
        }

        return $changes;
    }
}
