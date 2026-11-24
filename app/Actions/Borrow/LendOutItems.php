<?php

namespace App\Actions\Borrow;

use App\Enums\BorrowItemStatus;
use App\Enums\BorrowMailKind;
use App\Jobs\SendBorrowNotification;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestItem;
use App\Models\Setting;
use App\Models\User;
use App\Support\DatabaseError;
use App\Support\ItemQuantities;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 出借：勾選實際交出去的明細（bulk 可以少給），沒勾到的申請中明細一律退回。
 */
class LendOutItems
{
    public function __construct(private readonly BorrowUnits $units) {}

    /**
     * @param  array<string, mixed>  $data  LendBorrowRequestRequest::validated()：
     *                                      items（list<{id, units?}>，大量借用品項必須帶 units）、id_card_taken、deposit_taken、
     *                                      deposit_amount?、deposit_box_no?
     */
    public function handle(BorrowRequest $request, User $staff, array $data): BorrowRequest
    {
        try {
            return DB::transaction(function () use ($request, $staff, $data) {
                // 鎖住借用單，避免兩位值班人員同時按出借
                $request = BorrowRequest::query()->lockForUpdate()->findOrFail($request->id);

                if ($request->lent_at !== null) {
                    throw ValidationException::withMessages(['items' => '這張借用單已經出借過了。']);
                }

                $pending = $request->items()->where('status', BorrowItemStatus::Pending)->get()->keyBy('id');
                $selected = ItemQuantities::parse($data['items'] ?? []); // item id => quantity
                $unitCodes = ItemQuantities::units($data['items'] ?? []); // item id => 掃到的財產編號

                if ($pending->isEmpty()) {
                    throw ValidationException::withMessages(['items' => '這張借用單沒有可出借的項目。']);
                }

                $unknown = $selected->keys()->diff($pending->keys());
                if ($unknown->isNotEmpty()) {
                    throw ValidationException::withMessages(['items' => '選取的項目不屬於這張借用單，或已經處理過。']);
                }

                /** @var BorrowRequestItem $item */
                foreach ($pending as $id => $item) {
                    if (! $selected->has($id)) {
                        $item->update(['status' => BorrowItemStatus::Cancelled]);

                        continue;
                    }

                    $codes = $item->isBulk() ? ($unitCodes[$id] ?? []) : [];

                    // 大量借用品項一定要掃條碼：出借數量就是掃到的件數，每一件都有紀錄
                    if ($item->isBulk() && $codes === []) {
                        throw ValidationException::withMessages(['items' => '「'.$item->pool?->name.'」請掃描每一件的條碼再出借。']);
                    }

                    $quantity = $item->isBulk() ? count($codes) : 1;
                    if ($item->isBulk() && ($quantity < 1 || $quantity > $item->quantity)) {
                        throw ValidationException::withMessages(['items' => "「{$item->pool?->name}」掃了 {$quantity} 件，超過申請的 {$item->quantity} 件。"]);
                    }

                    $item->update([
                        'status' => BorrowItemStatus::Lent,
                        'quantity' => $item->isBulk() ? $quantity : 1,
                    ]);

                    $this->units->lend($item, $codes);
                }

                $request->update([
                    'lent_by' => $staff->id,
                    'lent_at' => now(),
                    'id_card_taken' => $data['id_card_taken'],
                    'deposit_taken' => $data['deposit_taken'],
                    'deposit_amount' => $data['deposit_taken']
                        ? ($data['deposit_amount'] ?? (int) Setting::get('borrow.deposit_amount', 100))
                        : null,
                    'deposit_box_no' => $data['deposit_box_no'] ?? null,
                ]);

                // 交易成功後才寄，避免出借其實回滾了卻已經通知借用人
                DB::afterCommit(fn () => SendBorrowNotification::dispatch($request, BorrowMailKind::Lent));

                return $request;
            });
        } catch (QueryException $e) {
            if (DatabaseError::is($e, DatabaseError::CHECK_VIOLATION, '數量不足')) {
                throw ValidationException::withMessages(['items' => '品項數量不足，無法出借。']);
            }

            throw $e;
        }
    }
}
