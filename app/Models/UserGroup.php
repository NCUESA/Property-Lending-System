<?php

namespace App\Models;

use Database\Factories\UserGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 */
#[Fillable(['name', 'description'])]
class UserGroup extends Model
{
    /** @use HasFactory<UserGroupFactory> */
    use HasFactory;

    /**
     * 直接加入的成員（不含子群組的成員）。
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_group_members')->withTimestamps();
    }

    /** @return BelongsToMany<UserGroup, $this> */
    public function subgroups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_subgroups', 'parent_group_id', 'child_group_id')
            ->withTimestamps();
    }

    /** @return BelongsToMany<UserGroup, $this> */
    public function parentGroups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_subgroups', 'child_group_id', 'parent_group_id')
            ->withTimestamps();
    }

    /** @return HasMany<UserGroupGrant, $this> */
    public function grants(): HasMany
    {
        return $this->hasMany(UserGroupGrant::class);
    }

    /**
     * 包含子群組在內的所有成員。
     *
     * @return BelongsToMany<User, $this>
     */
    public function effectiveMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_effective_groups');
    }
}
