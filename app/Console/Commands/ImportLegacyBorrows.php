<?php

namespace App\Console\Commands;

use App\Enums\BorrowItemStatus;
use App\Enums\Role;
use App\Models\Location;
use App\Models\Property;
use App\Models\User;
use App\Support\Legacy\LegacyPropertyCodes;
use App\Support\Legacy\MysqlDump;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 從舊系統 sa__property 的 mysqldump 匯入經手人（responsible_person）與借用紀錄
 * （borrowlist、borrow_item）。要先跑過 property:import-legacy，明細才對得到器材。
 *
 * - 借用單沿用舊單號，可以重複執行：同一張單會整張覆寫（含明細）。
 * - 資料庫裡一旦有不是從舊系統來的借用單，就代表新系統已經上線，拒絕執行，以免蓋掉新資料。
 * - 不會產生違規紀錄；但匯入時仍未歸還且已過期的單，會在下一次 borrow:record-overdue 被記違規。
 */
class ImportLegacyBorrows extends Command
{
    protected $signature = 'borrow:import-legacy
        {--sql=sqlexample : 舊系統 MySQL dump 的路徑}
        {--dry-run : 只輸出報告，不寫入資料庫}';

    protected $description = '匯入舊系統的經手人帳號與借用紀錄';

    /** 舊系統 borrow_place → locations.code */
    private const LOCATION_MAP = [
        '進德' => 'jinde',
        '寶山' => 'baosan',
    ];

    /**
     * 系統建置期間的測試單：單位或姓名是「測試／test／123／ㄐㄐ」、備註寫明測試，
     * 或是用幹部自己的聯絡資料填的假單。#3–5 的明細指向不存在的器材，
     * #11、#12 沒有明細，#12 的歸還日還早於借用日，都在這裡面。
     */
    private const TEST_REQUEST_IDS = [
        1, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 23, 24, 26,
        42, 44, 53, 58, 59, 61, 62, 69, 70, 144, 146, 165, 166, 167,
        205, 206, 331, 332, 335, 475, 494, 506, 507, 508,
    ];

    /** 同一張單重複列了同一件器材時，保留進度最後面的那一筆。 */
    private const STATUS_PRIORITY = ['cancelled' => 0, 'pending' => 1, 'returned' => 2, 'lent' => 3];

    private const BORROWLIST_COLUMNS = [
        'id', 'understand', 'borrow_place', 'borrow_department', 'borrow_person_name',
        'phone', 'email', 'borrow_date', 'returned_date',
        'sa_lending_person_name', 'sa_lending_date', 'sa_id_take', 'sa_deposit_take', 'sa_id_deposit_box_number',
        'sa_return_person_name', 'sa_returned_date', 'sa_id_returned', 'sa_deposit_returned',
        'sa_remark', 'created_at', 'updated_at', 'filling_time',
    ];

    private const BORROW_ITEM_COLUMNS = [
        'id', 'borrow_id', 'property_id', 'status', 'returned_date', 'created_at', 'updated_at',
    ];

    private const RESPONSIBLE_PERSON_COLUMNS = [
        'id', 'name', 'status', 'created_at', 'updated_at', 'stu_id', 'auth_level',
    ];

    /** @var list<string> */
    private array $warnings = [];

    public function handle(): int
    {
        try {
            $dump = MysqlDump::open(base_path((string) $this->option('sql')));
            $people = $dump->rows('responsible_person', self::RESPONSIBLE_PERSON_COLUMNS);
            $lists = $dump->rows('borrowlist', self::BORROWLIST_COLUMNS);
            $items = $dump->rows('borrow_item', self::BORROW_ITEM_COLUMNS);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf('舊系統：經手人 %d 位、借用單 %d 張、明細 %d 筆', count($people), count($lists), count($items)));

        $legacyIds = array_map(fn (array $r): int => (int) $r['id'], $lists);
        $foreign = DB::table('borrow_requests')->whereNotIn('id', $legacyIds)->count();

        if ($foreign > 0) {
            $this->error("borrow_requests 已有 {$foreign} 張不是舊系統的單，新系統應已上線，為避免覆寫已中止。");

            return self::FAILURE;
        }

        $locations = Location::query()->pluck('id', 'code');
        /** @var array<string, int> $placeIds 舊 borrow_place → locations.id */
        $placeIds = [];

        foreach (self::LOCATION_MAP as $place => $code) {
            if (! isset($locations[$code])) {
                $this->error("locations 裡找不到 code = {$code}（對應舊資料的「{$place}」），請先執行 LocationSeeder。");

                return self::FAILURE;
            }

            $placeIds[$place] = (int) $locations[$code];
        }

        $propertyIds = Property::query()->pluck('id', 'ssid');

        if ($propertyIds->isEmpty()) {
            $this->error('properties 是空的，請先執行 property:import-legacy。');

            return self::FAILURE;
        }

        $skip = array_flip(self::TEST_REQUEST_IDS);
        $lists = array_values(array_filter($lists, fn (array $r): bool => ! isset($skip[(int) $r['id']])));
        $itemsByRequest = $this->groupItems($items, $skip, $propertyIds->all());

        $requests = [];
        $requestItems = [];
        $handlerNames = array_column($people, 'name');

        foreach ($lists as $r) {
            $id = (int) $r['id'];

            if (empty($itemsByRequest[$id])) {
                $this->warnings[] = "#{$id} 沒有明細，略過";

                continue;
            }

            if (! isset($placeIds[(string) $r['borrow_place']])) {
                $this->error("#{$id} 的借用地點「{$r['borrow_place']}」沒有對應，請補上 LOCATION_MAP。");

                return self::FAILURE;
            }

            if ($r['returned_date'] < $r['borrow_date']) {
                $this->error("#{$id} 的歸還日早於借用日，請修正資料或加入 TEST_REQUEST_IDS。");

                return self::FAILURE;
            }

            $requests[$id] = $r;
            $requestItems[$id] = $itemsByRequest[$id];
        }

        if ($missing = $this->missingProperties($requestItems, $propertyIds->all())) {
            $this->error('下列明細的財產編號在 properties 裡找不到：'.implode('、', $missing));

            return self::FAILURE;
        }

        $unmatched = $this->unmatchedHandlers($requests, $handlerNames);
        $this->report($people, $requests, $requestItems, $unmatched, count($skip));

        if ($this->option('dry-run')) {
            $this->warn('--dry-run：未寫入資料庫。');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($people, $requests, $requestItems, $placeIds, $propertyIds) {
            $userIds = $this->importPeople($people);

            foreach ($requests as $id => $r) {
                $this->importRequest($id, $r, $requestItems[$id], $userIds, $placeIds, $propertyIds->all());
            }

            foreach (['borrow_requests', 'borrow_request_items'] as $table) {
                DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), greatest((SELECT max(id) FROM {$table}), 1))");
            }
        });

        $this->newLine();
        $this->info(sprintf(
            '匯入完成：借用單 %d 張、明細 %d 筆。',
            count($requests),
            array_sum(array_map('count', $requestItems)),
        ));

        return self::SUCCESS;
    }

    /**
     * 經手人以學號對應帳號，沒有就建立。SSO 第一次登入時會用學號綁定這個帳號。
     *
     * - 在職（status 1）：admin → admin，其餘 → staff。
     * - 離職（status 0）：borrower。不停用帳號，停用會讓他們連借東西都無法登入。
     * - 既有帳號只升不降，姓名不覆寫（以 SSO 為準）。
     *
     * @param  list<array<string, string|null>>  $people
     * @return array<string, int> 姓名 → users.id
     */
    private function importPeople(array $people): array
    {
        $ids = [];

        foreach ($people as $p) {
            $studentId = strtoupper(trim((string) $p['stu_id']));
            $role = $this->legacyRole($p);

            $user = User::query()->whereRaw('upper(student_id) = ?', [$studentId])->first()
                ?? new User(['student_id' => $studentId, 'name' => (string) $p['name']]);

            if (! $user->exists || $this->rank($role) > $this->rank($user->role)) {
                $user->role = $role;
            }

            $user->save();
            $ids[(string) $p['name']] = $user->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, string|null>  $r
     * @param  list<array<string, string|null>>  $items
     * @param  array<string, int>  $userIds
     * @param  array<string, int>  $placeIds
     * @param  array<string, int>  $propertyIds
     */
    private function importRequest(int $id, array $r, array $items, array $userIds, array $placeIds, array $propertyIds): void
    {
        $statuses = array_map(fn (array $i): BorrowItemStatus => BorrowItemStatus::fromLegacy((int) $i['status']), $items);
        $everLent = (bool) array_intersect(
            array_map(fn (BorrowItemStatus $s) => $s->value, $statuses),
            [BorrowItemStatus::Lent->value, BorrowItemStatus::Returned->value],
        );
        $stillLent = in_array(BorrowItemStatus::Lent, $statuses, true);

        $lender = $this->blankToNull($r['sa_lending_person_name']);
        $returner = $this->blankToNull($r['sa_return_person_name']);
        $lentAt = $everLent ? $this->dateTime($r['sa_lending_date']) : null;
        $closed = $everLent && ! $stillLent;
        $returnedAt = $closed ? $this->dateTime($r['sa_returned_date']) : null;

        $remark = $this->blankToNull($r['sa_remark']);

        // 整張都沒借出時，舊系統的「出借人」其實是處理（退回）申請的人，不寫進 lent_by
        if (! $everLent && $lender !== null) {
            $remark = trim(($remark ?? '')."\n舊系統處理人：{$lender}");
        }

        $createdAt = $this->dateTime($r['filling_time']);
        $updatedAt = $r['updated_at'] !== null
            ? CarbonImmutable::parse($r['updated_at'], 'UTC')->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s')
            : $createdAt;

        $boxNo = (int) $r['sa_id_deposit_box_number'];

        $row = [
            'id' => $id,
            'location_id' => $placeIds[(string) $r['borrow_place']],
            'user_id' => null,
            'borrower_department' => (string) $r['borrow_department'],
            'borrower_name' => (string) $r['borrow_person_name'],
            'phone' => (string) $r['phone'],
            'email' => (string) $r['email'],
            'borrow_date' => $r['borrow_date'],
            'due_date' => $r['returned_date'],
            'agreed_terms' => $r['understand'] === '1',

            'lent_by' => $lentAt !== null ? ($userIds[$lender] ?? null) : null,
            'lent_at' => $lentAt,
            'id_card_taken' => $everLent && $r['sa_id_take'] === '1',
            'deposit_taken' => $everLent && $r['sa_deposit_take'] === '1',
            'deposit_amount' => null, // 舊系統沒有記金額
            'deposit_box_no' => $everLent && $boxNo > 0 ? $boxNo : null,
            'legacy_lender_name' => $lentAt !== null && ! isset($userIds[$lender]) ? $lender : null,

            'returned_by' => $returnedAt !== null ? ($userIds[$returner] ?? null) : null,
            'returned_at' => $returnedAt,
            'id_card_returned' => $closed && $r['sa_id_returned'] === '1',
            'deposit_returned' => $closed && $r['sa_deposit_returned'] === '1',
            'legacy_returner_name' => $returnedAt !== null && ! isset($userIds[$returner]) ? $returner : null,

            'remark' => $remark,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];

        DB::table('borrow_requests')->upsert([$row], ['id']);
        DB::table('borrow_request_items')->where('borrow_request_id', $id)->delete();

        $rows = [];

        foreach ($items as $index => $i) {
            $status = $statuses[$index];
            $returned = $status === BorrowItemStatus::Returned;
            $itemReturnedAt = $returned ? ($this->dateTime($r['sa_returned_date']) ?? $lentAt) : null;

            $rows[] = [
                'id' => (int) $i['id'],
                'borrow_request_id' => $id,
                'property_id' => $propertyIds[LegacyPropertyCodes::forBorrowItem((string) $i['property_id'])],
                'property_pool_id' => null,
                'quantity' => 1,
                'returned_quantity' => $returned ? 1 : 0,
                'status' => $status->value,
                'returned_by' => $returned ? ($userIds[$returner] ?? null) : null,
                'returned_at' => $itemReturnedAt,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        DB::table('borrow_request_items')->insert($rows);
    }

    /**
     * @param  list<array<string, string|null>>  $items
     * @param  array<int, int>  $skip
     * @param  array<string, int>  $propertyIds
     * @return array<int, list<array<string, string|null>>>
     */
    private function groupItems(array $items, array $skip, array $propertyIds): array
    {
        $grouped = [];

        foreach ($items as $i) {
            $requestId = (int) $i['borrow_id'];

            if (isset($skip[$requestId])) {
                continue;
            }

            $ssid = LegacyPropertyCodes::forBorrowItem((string) $i['property_id']);
            $status = BorrowItemStatus::fromLegacy((int) $i['status'])->value;
            $existing = $grouped[$requestId][$ssid] ?? null;

            if ($existing !== null) {
                $this->warnings[] = "#{$requestId} 重複列了 {$ssid}，保留進度較後面的一筆";

                $existingStatus = BorrowItemStatus::fromLegacy((int) $existing['status'])->value;

                if (self::STATUS_PRIORITY[$status] <= self::STATUS_PRIORITY[$existingStatus]) {
                    continue;
                }
            }

            $grouped[$requestId][$ssid] = $i;
        }

        return array_map('array_values', $grouped);
    }

    /**
     * @param  array<int, list<array<string, string|null>>>  $requestItems
     * @param  array<string, int>  $propertyIds
     * @return list<string>
     */
    private function missingProperties(array $requestItems, array $propertyIds): array
    {
        $missing = [];

        foreach ($requestItems as $id => $items) {
            foreach ($items as $i) {
                $ssid = LegacyPropertyCodes::forBorrowItem((string) $i['property_id']);

                if (! isset($propertyIds[$ssid])) {
                    $missing[] = "#{$id} {$ssid}";
                }
            }
        }

        return $missing;
    }

    /**
     * @param  array<int, array<string, string|null>>  $requests
     * @param  list<string|null>  $handlerNames
     * @return array<string, int>
     */
    private function unmatchedHandlers(array $requests, array $handlerNames): array
    {
        $known = array_flip(array_map('strval', $handlerNames));
        $unmatched = [];

        foreach ($requests as $r) {
            foreach (['sa_lending_person_name', 'sa_return_person_name'] as $column) {
                $name = $this->blankToNull($r[$column]);

                if ($name !== null && ! isset($known[$name])) {
                    $unmatched[$name] = ($unmatched[$name] ?? 0) + 1;
                }
            }
        }

        return $unmatched;
    }

    /**
     * @param  list<array<string, string|null>>  $people
     * @param  array<int, array<string, string|null>>  $requests
     * @param  array<int, list<array<string, string|null>>>  $requestItems
     * @param  array<string, int>  $unmatched
     */
    private function report(array $people, array $requests, array $requestItems, array $unmatched, int $skipped): void
    {
        $roles = array_count_values(array_map(fn (array $p): string => $this->legacyRole($p)->value, $people));
        $this->line('經手人帳號：'.implode('、', array_map(fn ($role, $n) => "{$role} {$n} 位", array_keys($roles), $roles)));
        $this->line(sprintf('借用單：匯入 %d 張，略過測試單 %d 張', count($requests), $skipped));

        foreach ($this->warnings as $warning) {
            $this->warn("  {$warning}");
        }

        if ($unmatched !== []) {
            $this->newLine();
            $this->warn('經手人姓名對不到帳號，保留在 legacy_lender_name / legacy_returner_name：');

            foreach ($unmatched as $name => $count) {
                $this->line("  {$name}（{$count} 次）");
            }
        }

        $open = [];
        $today = CarbonImmutable::today()->toDateString();

        foreach ($requests as $id => $r) {
            $lent = array_filter($requestItems[$id], fn (array $i): bool => $i['status'] === '1');

            if ($lent !== []) {
                $overdue = $r['returned_date'] < $today ? '，已逾期' : '';
                $open[] = sprintf('#%d 借出 %d 件，應於 %s 歸還%s', $id, count($lent), $r['returned_date'], $overdue);
            }
        }

        if ($open !== []) {
            $this->newLine();
            $this->warn('仍未歸還（逾期的單會在下一次 borrow:record-overdue 記違規）：');

            foreach ($open as $line) {
                $this->line("  {$line}");
            }
        }
    }

    /** @param  array<string, string|null>  $person */
    private function legacyRole(array $person): Role
    {
        if ($person['status'] !== '1') {
            return Role::Borrower;
        }

        return $person['auth_level'] === 'admin' ? Role::Admin : Role::Staff;
    }

    private function rank(Role $role): int
    {
        return match ($role) {
            Role::Borrower => 0,
            Role::Staff => 1,
            Role::Admin => 2,
        };
    }

    /** 舊系統的日期沒有時間，一律記成當天 00:00。 */
    private function dateTime(?string $value): ?string
    {
        $value = $this->blankToNull($value);

        if ($value === null) {
            return null;
        }

        return strlen($value) === 10 ? "{$value} 00:00:00" : $value;
    }

    private function blankToNull(?string $value): ?string
    {
        return ($value === null || trim($value) === '') ? null : trim($value);
    }
}
