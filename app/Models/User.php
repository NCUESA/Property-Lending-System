<?php

namespace App\Models;

use App\Enums\Role;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string|null $student_id
 * @property string $name
 * @property string|null $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string|null $sso_id
 * @property Role $role
 * @property bool $is_active
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['student_id', 'name', 'email', 'sso_id', 'role', 'is_active'])]
#[Hidden(['sso_id', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * 與資料庫預設值一致，讓 create() 後的 model 不必 refresh 就有正確的值。
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'borrower',
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isStaffOrAbove(): bool
    {
        return $this->role->isStaffOrAbove();
    }

    /**
     * 直接加入的群組。
     *
     * @return BelongsToMany<UserGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_members')->withTimestamps();
    }

    /**
     * 實際所屬的所有群組（含透過子群組繼承的上層群組）。
     *
     * @return BelongsToMany<UserGroup, $this>
     */
    public function effectiveGroups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'user_effective_groups');
    }
}
