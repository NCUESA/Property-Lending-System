<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $ssid_prefix
 * @property bool $is_pickup_point
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['code', 'name', 'ssid_prefix', 'is_pickup_point', 'is_active', 'sort_order'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_pickup_point' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * 由此取用地點負責出借的存放地點（不含自己）。互連關係是有向圖，存在 location_links。
     *
     * @return BelongsToMany<Location, $this>
     */
    public function storageLocations(): BelongsToMany
    {
        return $this->belongsToMany(
            Location::class,
            'location_links',
            'pickup_location_id',
            'storage_location_id',
        )->withTimestamps();
    }

    /**
     * 反方向：哪些取用地點可以借出這裡的器材。
     *
     * @return BelongsToMany<Location, $this>
     */
    public function pickupLocations(): BelongsToMany
    {
        return $this->belongsToMany(
            Location::class,
            'location_links',
            'storage_location_id',
            'pickup_location_id',
        )->withTimestamps();
    }

    /** @return HasMany<Property, $this> */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /** @return HasMany<PropertyPool, $this> */
    public function pools(): HasMany
    {
        return $this->hasMany(PropertyPool::class);
    }

    /** 可以在前台選取的取用地點：啟用中且標記為取用點。 */
    /** @param Builder<Location> $query */
    public function scopePickupPoints(Builder $query): void
    {
        $query->where('is_pickup_point', true)->where('is_active', true)->orderBy('sort_order');
    }

    /** @param Builder<Location> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * 此取用地點可借出的所有存放地點 id（含自己）。只看直接相連的一層。
     *
     * @return list<int>
     */
    public function servedLocationIds(): array
    {
        return [
            $this->id,
            ...$this->storageLocations()->active()->pluck('locations.id')->map(fn ($id): int => (int) $id)->values()->all(),
        ];
    }
}
