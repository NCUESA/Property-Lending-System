<?php

namespace App\Models;

use App\Enums\BorrowItemStatus;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ssid
 * @property int|null $sequence_no
 * @property string $category
 * @property string $name
 * @property string|null $second_name
 * @property string|null $spec
 * @property int|null $price
 * @property string|null $department
 * @property string|null $custodian
 * @property int $location_id
 * @property Carbon|null $acquired_on
 * @property bool $is_school_owned
 * @property string|null $image_path
 * @property bool $is_lendable
 * @property string|null $remark
 */
#[Fillable([
    'ssid', 'sequence_no', 'category', 'name', 'second_name', 'spec', 'price',
    'department', 'custodian', 'location_id', 'acquired_on', 'is_school_owned',
    'image_path', 'is_lendable', 'remark',
])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'acquired_on' => 'date',
            'is_school_owned' => 'boolean',
            'is_lendable' => 'boolean',
            'price' => 'integer',
            'sequence_no' => 'integer',
        ];
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
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

    /**
     * 可借：開放借用，且沒有進行中的借用。
     *
     * @param  Builder<Property>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_lendable', true)->whereDoesntHave('activeBorrowItem');
    }

    /**
     * 可從指定取用地點借出的器材。
     *
     * @param  Builder<Property>  $query
     */
    public function scopePickupableFrom(Builder $query, Location $pickup): void
    {
        $query->whereIn('location_id', $pickup->servedLocationIds());
    }
}
