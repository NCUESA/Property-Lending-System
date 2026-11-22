<?php

namespace App\Models;

use App\Enums\BorrowItemStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * @property int $id
 * @property string $ssid
 * @property int $property_subcategory_id
 * @property int|null $sequence_no
 * @property string $name
 * @property string|null $second_name
 * @property string|null $spec
 * @property int|null $price
 * @property string|null $department
 * @property string|null $custodian
 * @property int $location_id
 * @property int|null $property_pool_id
 * @property CarbonImmutable|null $acquired_on
 * @property bool $is_school_owned
 * @property int $auth_level
 * @property string|null $image_path
 * @property bool $is_lendable
 * @property string|null $remark
 */
#[Fillable([
    'ssid', 'property_subcategory_id', 'sequence_no', 'name', 'second_name', 'spec', 'price',
    'department', 'custodian', 'location_id', 'property_pool_id', 'acquired_on',
    'is_school_owned', 'auth_level', 'image_path', 'is_lendable', 'remark',
])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'acquired_on' => 'date:Y-m-d',
            'is_school_owned' => 'boolean',
            'is_lendable' => 'boolean',
            'price' => 'integer',
            'sequence_no' => 'integer',
            'auth_level' => 'integer',
        ];
    }

    /** @return BelongsTo<PropertySubcategory, $this> */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(PropertySubcategory::class, 'property_subcategory_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<PropertyPool, $this> */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(PropertyPool::class, 'property_pool_id');
    }

    /** @return HasMany<BorrowRequestItem, $this> */
    public function borrowItems(): HasMany
    {
        return $this->hasMany(BorrowRequestItem::class);
    }

    /**
     * 目前進行中（申請中或已借出）的借用明細，最多一筆。
     *
     * @return HasOne<BorrowRequestItem, $this>
     */
    public function activeBorrowItem(): HasOne
    {
        return $this->hasOne(BorrowRequestItem::class)
            ->whereIn('status', BorrowItemStatus::ACTIVE);
    }

    public function isBulk(): bool
    {
        return $this->property_pool_id !== null;
    }

    /**
     * 需要逐件挑選的器材（不屬於 bulk 品項池）。
     *
     * @param  Builder<Property>  $query
     */
    public function scopeSerial(Builder $query): void
    {
        $query->whereNull('properties.property_pool_id');
    }

    /**
     * 可借：開放借用，且沒有進行中的借用。
     *
     * @param  Builder<Property>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('properties.is_lendable', true)->whereDoesntHave('activeBorrowItem');
    }

    /**
     * 可從指定取用地點借出的器材。
     *
     * @param  Builder<Property>  $query
     */
    public function scopePickupableFrom(Builder $query, Location $pickup): void
    {
        $query->whereIn('properties.location_id', $pickup->servedLocationIds());
    }

    /**
     * 此使用者有權借用的器材：auth_level = 0，或使用者所屬群組（含上層群組）
     * 授權了這件器材、它的次要類別或主要類別。$user 為 null 代表未登入的訪客。
     *
     * @param  Builder<Property>  $query
     */
    public function scopeBorrowableBy(Builder $query, ?User $user): void
    {
        $query->where(function (Builder $q) use ($user) {
            $q->where('properties.auth_level', 0);

            if ($user !== null) {
                $q->orWhereExists(fn (QueryBuilder $sub) => UserGroupGrant::queryForUser($sub, $user, 'properties.auth_level')
                    ->where(fn (QueryBuilder $w) => $w
                        ->whereColumn('g.property_id', 'properties.id')
                        ->orWhereColumn('g.property_subcategory_id', 'properties.property_subcategory_id')
                        ->orWhereIn('g.property_category_id', fn (QueryBuilder $c) => $c
                            ->select('property_category_id')
                            ->from('property_subcategories')
                            ->whereColumn('property_subcategories.id', 'properties.property_subcategory_id'))));
            }
        });
    }
}
