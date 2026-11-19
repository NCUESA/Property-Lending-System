<?php

namespace App\Models;

use Database\Factories\PropertySubcategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 次要類別（財產編號第 5 碼）。
 *
 * @property int $id
 * @property int $property_category_id
 * @property string $code
 * @property string $name
 * @property-read PropertyCategory $category
 */
#[Fillable(['property_category_id', 'code', 'name'])]
class PropertySubcategory extends Model
{
    /** @use HasFactory<PropertySubcategoryFactory> */
    use HasFactory;

    /** @return BelongsTo<PropertyCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class, 'property_category_id');
    }

    /** @return HasMany<Property, $this> */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /** 主類 + 次類，共 4 碼，例如 2000。 */
    public function fullCode(): string
    {
        return $this->category->code.$this->code;
    }
}
