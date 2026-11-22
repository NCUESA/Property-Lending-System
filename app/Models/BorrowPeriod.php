<?php

namespace App\Models;

use App\Enums\BorrowOpenMode;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * 開放／暫停借用期間。期間不可重疊（資料庫 EXCLUDE constraint）。
 *
 * @property int $id
 * @property string $name
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property bool $is_open
 * @property string|null $note
 */
#[Fillable(['name', 'starts_on', 'ends_on', 'is_open', 'note'])]
class BorrowPeriod extends Model
{
    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
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

    /** 目前的開放判斷方式。 */
    public static function mode(): BorrowOpenMode
    {
        return BorrowOpenMode::tryFrom((string) Setting::get('borrow.open_mode', BorrowOpenMode::AlwaysOpen->value))
            ?? BorrowOpenMode::AlwaysOpen;
    }

    /**
     * 某天是否開放借用。
     *
     * always_open：一律開放，連期間都不看。
     * periods：只有落在設為開放的期間內才算開放，期間外一律關閉。
     */
    public static function isOpenOn(CarbonInterface $date): bool
    {
        if (static::mode() === BorrowOpenMode::AlwaysOpen) {
            return true;
        }

        return static::covering($date)?->is_open === true;
    }

    /**
     * 區間內所有暫停借用的日期（Y-m-d）。給前台日曆把這些日子標成不可選用。
     *
     * 一次把重疊的期間撈出來再逐日比對，避免每天都打一次 DB。
     *
     * @return list<string>
     */
    public static function closedDatesBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        if (static::mode() === BorrowOpenMode::AlwaysOpen) {
            return [];
        }

        $periods = static::query()
            ->whereDate('ends_on', '>=', $from)
            ->whereDate('starts_on', '<=', $to)
            ->get(['starts_on', 'ends_on', 'is_open']);

        $closed = [];

        for ($day = CarbonImmutable::parse($from)->startOfDay(); $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $period = $periods->first(
                fn (self $p) => $p->starts_on->lessThanOrEqualTo($day) && $p->ends_on->greaterThanOrEqualTo($day)
            );

            if ($period === null || ! $period->is_open) {
                $closed[] = $day->toDateString();
            }
        }

        return $closed;
    }
}
