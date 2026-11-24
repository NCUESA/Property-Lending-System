<?php

namespace App\Actions\Borrow;

use App\Models\BorrowItemUnit;
use App\Models\BorrowRequestItem;
use App\Models\Property;
use App\Models\User;
use App\Support\DatabaseError;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * 大量借用品項的單件紀錄：出借時記下掃到的器材，歸還時逐件結清。
 * 呼叫端負責交易（出借、歸還的 DB::transaction 內）。
 */
class BorrowUnits
{
    /**
     * 記錄出借的單件器材。
     *
     * @param  list<string>  $codes  財產編號
     */
    public function lend(BorrowRequestItem $item, array $codes): void
    {
        if ($codes === []) {
            return;
        }

        $properties = Property::query()
            ->whereIn('ssid', $codes)
            ->where('property_pool_id', $item->property_pool_id)
            ->where('is_lendable', true)
            ->pluck('id', 'ssid');

        $unknown = array_values(array_diff($codes, $properties->keys()->all()));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'items' => implode('、', $unknown).' 不屬於「'.$item->pool?->name.'」，或不開放借用。',
            ]);
        }

        foreach ($properties as $ssid => $propertyId) {
            try {
                BorrowItemUnit::query()->create([
                    'borrow_request_item_id' => $item->id,
                    'property_id' => $propertyId,
                ]);
            } catch (QueryException $e) {
                if (DatabaseError::is($e, DatabaseError::UNIQUE_VIOLATION, 'one_open_unit_per_property')) {
                    throw ValidationException::withMessages(['items' => "{$ssid} 目前已經借出，還沒歸還。"]);
                }

                throw $e;
            }
        }
    }

    /**
     * 結清歸還的單件器材，回傳這次歸還的數量。
     *
     * - 有掃條碼：逐件結清，數量就是掃到的件數（加上沒有紀錄、只填數量的部分）。
     * - 沒掃條碼：全部還清時一併結清；只還一部分時，只能還「沒有逐件紀錄」的那些。
     *
     * @param  list<string>  $codes
     */
    public function return(BorrowRequestItem $item, array $codes, int $quantity, User $staff): int
    {
        $open = $item->units()->whereNull('returned_at')->with('property:id,ssid')->get()
            ->keyBy(fn (BorrowItemUnit $u) => $u->property->ssid);
        $untracked = $item->outstandingQuantity() - $open->count();

        if ($codes !== []) {
            $notOpen = array_values(array_diff($codes, $open->keys()->all()));

            // 出借時沒掃到的，歸還時掃到只算數量
            if ($notOpen !== [] && count($notOpen) > $untracked) {
                throw ValidationException::withMessages([
                    'items' => implode('、', $notOpen).' 不在這張單借出的「'.$item->pool?->name.'」裡，或已經歸還。',
                ]);
            }

            // Eloquent Collection 的 only() 是比對主鍵，這裡要比對的是編號（collection 的 key）
            $closing = $open->toBase()->only($codes);
            $quantity = max($quantity, count($codes));
        } elseif ($quantity === $item->outstandingQuantity()) {
            $closing = $open;
        } elseif ($quantity > $untracked) {
            throw ValidationException::withMessages([
                'items' => '「'.$item->pool?->name.'」有記錄借出的是哪幾件，部分歸還請掃描或勾選歸還的器材。',
            ]);
        } else {
            $closing = collect();
        }

        BorrowItemUnit::query()
            ->whereIn('id', $closing->pluck('id'))
            ->update(['returned_at' => now(), 'returned_by' => $staff->id, 'updated_at' => now()]);

        return $quantity;
    }
}
