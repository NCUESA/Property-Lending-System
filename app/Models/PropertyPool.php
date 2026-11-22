<?php

namespace App\Models;

use App\Enums\BorrowItemStatus;
use Database\Factories\PropertyPoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Bulk 品項池，例如「進德 折疊椅」。
 *
 * @property int $id
 * @property string $name
 * @property int $location_id
 * @property int $auth_level
 * @property string|null $image_path
 * @property string|null $remark
 * @property-read int|null $available_quantity 由 scopeWithAvailableQuantity 帶出
 */
#[Fillable(['name', 'location_id', 'auth_level', 'image_path', 'remark'])]
class PropertyPool extends Model
{
    /** @use HasFactory<PropertyPoolFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'auth_level' => 'integer',
        ];
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return HasMany<Property, $this> */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /** @return HasMany<BorrowRequestItem, $this> */
    public function borrowItems(): HasMany
    {
        return $this->hasMany(BorrowRequestItem::class);
    }

    /**
     * 帶出 available_quantity：可借件數 − 進行中未歸還的數量。計算方式與資料庫 trigger 相同。
     *
     * @param  Builder<PropertyPool>  $query
     */
    public function scopeWithAvailableQuantity(Builder $query): void
    {
        $query->addSelect('property_pools.*')->selectSub(function (QueryBuilder $sub) {
            $sub->selectRaw(
                '(SELECT count(*) FROM properties p WHERE p.property_pool_id = property_pools.id AND p.is_lendable)'
                .' - (SELECT coalesce(sum(i.quantity - i.returned_quantity), 0) FROM borrow_request_items i'
                .' WHERE i.property_pool_id = property_pools.id AND i.status IN (?, ?))',
                array_map(fn (BorrowItemStatus $s) => $s->value, BorrowItemStatus::ACTIVE),
            );
        }, 'available_quantity');
    }

    /**
     * @param  Builder<PropertyPool>  $query
     */
    public function scopeBorrowableBy(Builder $query, ?User $user): void
    {
        $query->where(function (Builder $q) use ($user) {
            $q->where('property_pools.auth_level', 0);

            if ($user !== null) {
                $q->orWhereExists(fn (QueryBuilder $sub) => UserGroupGrant::queryForUser($sub, $user, 'property_pools.auth_level')
                    ->whereColumn('g.property_pool_id', 'property_pools.id'));
            }
        });
    }
}
