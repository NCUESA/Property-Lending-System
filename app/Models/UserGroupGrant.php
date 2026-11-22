<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * 群組的借用授權，四擇一：單一器材、主要類別、次要類別、bulk 品項池。
 *
 * @property int $id
 * @property int $user_group_id
 * @property int|null $property_id
 * @property int|null $property_category_id
 * @property int|null $property_subcategory_id
 * @property int|null $property_pool_id
 */
#[Fillable(['user_group_id', 'property_id', 'property_category_id', 'property_subcategory_id', 'property_pool_id'])]
class UserGroupGrant extends Model
{
    /** @return BelongsTo<UserGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<PropertyCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class, 'property_category_id');
    }

    /** @return BelongsTo<PropertySubcategory, $this> */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(PropertySubcategory::class, 'property_subcategory_id');
    }

    /** @return BelongsTo<PropertyPool, $this> */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(PropertyPool::class, 'property_pool_id');
    }

    /**
     * 給 whereExists 用的子查詢：此使用者（含繼承自上層群組）擁有、而且等級夠的授權，別名為 g。
     *
     * @param  string  $levelColumn  外層要比對的器材等級欄位，例如 properties.auth_level
     */
    public static function queryForUser(QueryBuilder $sub, User $user, string $levelColumn): QueryBuilder
    {
        return $sub->selectRaw('1')
            ->from('user_group_grants as g')
            ->join('user_effective_groups as eg', 'eg.user_group_id', '=', 'g.user_group_id')
            ->join('user_groups as ug', 'ug.id', '=', 'g.user_group_id')
            ->where('eg.user_id', $user->id)
            // 範圍涵蓋還不夠，群組等級也要夠高
            ->whereColumn('ug.auth_level', '>=', $levelColumn);
    }
}
