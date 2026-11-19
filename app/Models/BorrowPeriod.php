<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * 開放／暫停借用期間。期間不可重疊（資料庫 EXCLUDE constraint）。
 *
 * @property int $id
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property bool $is_open
 * @property string|null $note
 */
#[Fillable(['name', 'starts_on', 'ends_on', 'is_open', 'note'])]
class BorrowPeriod extends Model
{
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_open' => 'boolean',
        ];
    }

    public static function covering(CarbonInterface $date): ?self
    {
        return static::query()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();
    }

    /** 某天是否開放借用：落在某個期間內就看該期間，否則看 settings 的 borrow.default_open。 */
    public static function isOpenOn(CarbonInterface $date): bool
    {
        $period = static::covering($date);

        return $period !== null
            ? $period->is_open
            : (bool) Setting::get('borrow.default_open', true);
    }
}
