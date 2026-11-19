<?php

namespace App\Actions\Borrow;

use App\Enums\BorrowItemStatus;
use App\Models\BorrowRequest;
use App\Models\BorrowRequestItem;
use App\Models\User;
use App\Support\ItemQuantities;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 歸還：可以部分歸還（逐件，或 bulk 只還一部分）。所有借出項目都還完時整張單結案。
 * 證件、押金可以和器材分開退還。
 */
class ReturnItems
{
    /**
     * @param  array<string, mixed>  $data  ReturnBorrowRequestRequest::validated()：
     *                                      items?（list<{id, quantity?}>）、id_card_returned?、deposit_returned?
     */
    public function handle(BorrowRequest $request, User $staff, array $data): BorrowRequest
    {
        return DB::transaction(function () use ($request, $staff, $data) {
            $request = BorrowRequest::query()->lockForUpdate()->findOrFail($request->id);

            $selected = ItemQuantities::parse($data['items'] ?? []); // item id => quantity
            $returnsDeposit = ($data['id_card_returned'] ?? false) || ($data['deposit_returned'] ?? false);

            if ($selected->count() === 0 && ! $returnsDeposit) {
                throw ValidationException::withMessages(['items' => '請選擇要歸還的項目。']);
            }

            $lent = $request->items()->where('status', BorrowItemStatus::Lent)->get()->keyBy('id');

            if ($selected->keys()->diff($lent->keys())->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => '選取的項目不是借出中的狀態，可能已經歸還過了。']);
            }

            foreach ($selected as $id => $requested) {
                /** @var BorrowRequestItem $item */
                $item = $lent[$id];
                $quantity = $requested ?? $item->outstandingQuantity();

                if ($quantity < 1 || $quantity > $item->outstandingQuantity()) {
                    throw ValidationException::withMessages(['items' => "歸還數量需介於 1 到 {$item->outstandingQuantity()}。"]);
                }

                $returnedQuantity = $item->returned_quantity + $quantity;

                $item->update([
                    'returned_quantity' => $returnedQuantity,
                    'status' => $returnedQuantity === $item->quantity ? BorrowItemStatus::Returned : BorrowItemStatus::Lent,
                    'returned_by' => $staff->id,
                    'returned_at' => now(),
                ]);
            }

            $request->fill(array_filter([
                'id_card_returned' => ($data['id_card_returned'] ?? false) ?: null,
                'deposit_returned' => ($data['deposit_returned'] ?? false) ?: null,
            ]));

            $allReturned = ! $request->items()->where('status', BorrowItemStatus::Lent)->exists();
            if ($allReturned && $request->returned_at === null && $request->lent_at !== null) {
                $request->returned_by = $staff->id;
                $request->returned_at = now();
            }

            $request->save();

            return $request;
        });
    }
}
