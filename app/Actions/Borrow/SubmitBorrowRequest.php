<?php

namespace App\Actions\Borrow;

use App\Enums\BorrowMailKind;
use App\Jobs\SendBorrowNotification;
use App\Models\BorrowPeriod;
use App\Models\BorrowRequest;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyPool;
use App\Models\User;
use App\Support\DatabaseError;
use App\Support\ItemQuantities;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 送出借用申請。
 *
 * 格式驗證在 StoreBorrowRequestRequest；這裡檢查需要查資料庫的業務規則，
 * 「是否已被借走」與「bulk 數量是否足夠」交給資料庫約束在寫入時把關。
 */
class SubmitBorrowRequest
{
    /**
     * @param  array<string, mixed>  $data  StoreBorrowRequestRequest::validated()：
     *                                      location_id、borrower_*、phone、email、borrow_date、due_date、
     *                                      property_ids?（list<int>）、pools?（list<{id, quantity}>）
     */
    public function handle(array $data, ?User $user): BorrowRequest
    {
        $location = Location::pickupPoints()->findOrFail((int) $data['location_id']);
        $propertyIds = array_values(array_unique(array_map('intval', (array) ($data['property_ids'] ?? []))));
        $pools = ItemQuantities::parse($data['pools'] ?? []); // pool id => quantity

        // 取件日與歸還日都要是開放的日子（中間跨到休館日沒關係，人不用來櫃檯）
        foreach (['borrow_date' => '借用', 'due_date' => '歸還'] as $field => $what) {
            if (! BorrowPeriod::isOpenOn(CarbonImmutable::parse((string) $data[$field]))) {
                throw ValidationException::withMessages([
                    $field => "此期間暫停借用，無法於這天{$what}，請留意學生會公告。",
                ]);
            }
        }

        $this->ensurePropertiesBorrowable($propertyIds, $location, $user);
        $this->ensurePoolsBorrowable(array_values($pools->keys()->all()), $location, $user);

        try {
            return DB::transaction(function () use ($data, $user, $location, $propertyIds, $pools) {
                $request = BorrowRequest::create([
                    'location_id' => $location->id,
                    'user_id' => $user?->id,
                    'borrower_department' => $data['borrower_department'],
                    'borrower_name' => $data['borrower_name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'borrow_date' => $data['borrow_date'],
                    'due_date' => $data['due_date'],
                    'agreed_terms' => true,
                ]);

                foreach ($propertyIds as $propertyId) {
                    $request->items()->create(['property_id' => $propertyId]);
                }

                foreach ($pools as $poolId => $quantity) {
                    $request->items()->create(['property_pool_id' => $poolId, 'quantity' => $quantity ?? 1]);
                }

                // 交易成功後才寄，避免申請其實失敗卻先寄出確認信
                DB::afterCommit(fn () => SendBorrowNotification::dispatch($request, BorrowMailKind::Submitted));

                return $request;
            });
        } catch (QueryException $e) {
            if (DatabaseError::is($e, DatabaseError::UNIQUE_VIOLATION, 'one_active_borrow_per_property')) {
                throw ValidationException::withMessages(['property_ids' => '有器材剛剛被其他人借走了，請重新選擇。']);
            }
            if (DatabaseError::is($e, DatabaseError::CHECK_VIOLATION, '數量不足')) {
                throw ValidationException::withMessages(['pools' => '數量不足，可能剛被其他人借走，請調整數量。']);
            }

            throw $e;
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function ensurePropertiesBorrowable(array $ids, Location $location, ?User $user): void
    {
        if ($ids === []) {
            return;
        }

        $allowed = Property::query()
            ->whereKey($ids)
            ->serial()
            ->where('is_lendable', true)
            ->pickupableFrom($location)
            ->borrowableBy($user)
            ->pluck('id')
            ->all();

        $denied = array_diff($ids, $allowed);

        if ($denied !== []) {
            $ssids = Property::whereKey($denied)->orderBy('ssid')->pluck('ssid')->join('、');

            throw ValidationException::withMessages([
                'property_ids' => $user === null
                    ? "以下器材無法借用或需要登入：{$ssids}"
                    : "以下器材無法借用：{$ssids}",
            ]);
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function ensurePoolsBorrowable(array $ids, Location $location, ?User $user): void
    {
        if ($ids === []) {
            return;
        }

        $allowed = PropertyPool::query()
            ->whereKey($ids)
            ->where('location_id', $location->id)
            ->borrowableBy($user)
            ->count();

        if ($allowed !== count($ids)) {
            throw ValidationException::withMessages([
                'pools' => $user === null ? '部分品項無法借用或需要登入。' : '部分品項無法借用。',
            ]);
        }
    }
}
