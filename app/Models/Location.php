<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_pickup_point
 * @property int|null $pickup_location_id
 * @property int $sort_order
 */
#[Fillable(['code', 'name', 'is_pickup_point', 'pickup_location_id', 'sort_order'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_pickup_point' => 'boolean',
        ];
    }

    /** @return BelongsTo<Location, $this> */
    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'pickup_location_id');
    }

    /**
     * 由此取用地點負責出借的存放地點（不含自己）。
     *
     * @return HasMany<Location, $this>
     */
    public function storageLocations(): HasMany
    {
        return $this->hasMany(Location::class, 'pickup_location_id');
    }

    /** @return HasMany<Property, $this> */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /** @param Builder<Location> $query */
    public function scopePickupPoints(Builder $query): void
    {
        $query->where('is_pickup_point', true)->orderBy('sort_order');
    }

    /**
     * 此取用地點可借出的所有存放地點 id（含自己）。
     *
     * @return list<int>
     */
    public function servedLocationIds(): array
    {
        return [$this->id, ...$this->storageLocations()->pluck('id')->map(fn ($id): int => (int) $id)->values()->all()];
    }
}
