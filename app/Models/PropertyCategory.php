<?php

namespace App\Models;

use Database\Factories\PropertyCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 主要類別（財產編號第 2-4 碼）。
 *
 * @property int $id
 * @property string $code
 * @property string $name
 */
#[Fillable(['code', 'name'])]
class PropertyCategory extends Model
{
    /** @use HasFactory<PropertyCategoryFactory> */
    use HasFactory;

    /** @return HasMany<PropertySubcategory, $this> */
    public function subcategories(): HasMany
    {
        return $this->hasMany(PropertySubcategory::class);
    }
}
