<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $alias
 * @property string $canonical
 */
#[Fillable(['alias', 'canonical'])]
class DepartmentAlias extends Model
{
    /**
     * 統計用的單位名稱（SQL 運算式）。查詢要先 LEFT JOIN department_aliases AS da。
     */
    public const CANONICAL_SQL = 'coalesce(da.canonical, btrim(r.borrower_department))';

    /** LEFT JOIN 條件：借用單的別名 r 要對到 da。 */
    public const JOIN_SQL = 'da.alias = btrim(r.borrower_department)';
}
