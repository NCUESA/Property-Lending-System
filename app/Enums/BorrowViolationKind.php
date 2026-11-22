<?php

namespace App\Enums;

use App\Models\Setting;

/**
 * 違規紀錄的種類。
 *
 * 每一種都有自己的「次數上限」與「封鎖天數」，存在 settings 裡，
 * 後台「違規與封鎖」那頁可以調。上限設 0 代表只記錄、不封鎖。
 */
enum BorrowViolationKind: string
{
    case SelfCancel = 'self_cancel';      // 借用人自己取消申請
    case Expired = 'expired';             // 借用日已過仍未取件，被排程退回
    case Overdue = 'overdue';             // 超過歸還日仍有器材未還
    case ReturnedLate = 'returned_late';  // 還是還了，但超過歸還日

    public function label(): string
    {
        return match ($this) {
            self::SelfCancel => '自行取消',
            self::Expired => '逾期未取',
            self::Overdue => '逾期未還',
            self::ReturnedLate => '逾期歸還',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SelfCancel => '借用人在「我的借用」自行取消申請（值班人員代為退回不算）',
            self::Expired => '借用日已過仍未取件，被系統退回',
            self::Overdue => '超過歸還日仍有器材未歸還',
            self::ReturnedLate => '器材已歸還，但超過了歸還日',
        };
    }

    /** settings 的 key，例如 borrow.violation.self_cancel.limit */
    public function settingKey(string $field): string
    {
        return "borrow.violation.{$this->value}.{$field}";
    }

    /** 達到幾次就封鎖；0 代表只記錄不封鎖。 */
    public function limit(): int
    {
        return max(0, (int) Setting::get($this->settingKey('limit'), $this->defaultLimit()));
    }

    public function blockDays(): int
    {
        return max(1, (int) Setting::get($this->settingKey('block_days'), $this->defaultBlockDays()));
    }

    /** 預設只有「自行取消」會封鎖，其餘先純記錄，要不要封由管理員決定。 */
    public function defaultLimit(): int
    {
        return $this === self::SelfCancel ? 2 : 0;
    }

    public function defaultBlockDays(): int
    {
        return 1;
    }

    /**
     * 這一種違規會取代掉同一張單的哪一種舊紀錄。
     *
     * 逾期未還是「還在外面」的狀態，每天掃一次；東西還回來之後同一件事
     * 應該只留下「逾期歸還」一筆，否則同一次遲還會被記成兩筆違規。
     */
    public function supersedes(): ?self
    {
        return $this === self::ReturnedLate ? self::Overdue : null;
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $kind) => ['value' => $kind->value, 'label' => $kind->label()],
            self::cases(),
        );
    }
}
