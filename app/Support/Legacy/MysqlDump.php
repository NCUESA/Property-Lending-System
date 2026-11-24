<?php

namespace App\Support\Legacy;

use RuntimeException;

/**
 * 讀取舊系統（sa__property）的 mysqldump，取出指定資料表的 INSERT 資料。
 *
 * mysqldump 的 INSERT 不帶欄位名稱，所以呼叫端要給欄位順序（照 CREATE TABLE）。
 */
final class MysqlDump
{
    private function __construct(private readonly string $sql) {}

    public static function open(string $path): self
    {
        if (! is_readable($path)) {
            throw new RuntimeException("讀不到檔案：{$path}");
        }

        $sql = file_get_contents($path);

        if ($sql === false) {
            throw new RuntimeException("讀取失敗：{$path}");
        }

        return new self($sql);
    }

    public static function fromString(string $sql): self
    {
        return new self($sql);
    }

    /**
     * @param  list<string>  $columns
     * @return list<array<string, string|null>>
     */
    public function rows(string $table, array $columns): array
    {
        $prefix = "INSERT INTO `{$table}` VALUES ";
        $rows = [];

        foreach (explode("\n", $this->sql) as $line) {
            if (! str_starts_with($line, $prefix)) {
                continue;
            }

            foreach ($this->parseValues(rtrim(substr($line, strlen($prefix)), ";\r")) as $values) {
                if (count($values) !== count($columns)) {
                    throw new RuntimeException(sprintf(
                        '%s 的欄位數不符：預期 %d 欄，實際 %d 欄（id = %s）',
                        $table, count($columns), count($values), $values[0] ?? '?',
                    ));
                }

                $rows[] = array_combine($columns, $values);
            }
        }

        return $rows;
    }

    /**
     * 字串用單引號並以反斜線轉義，NULL 不加引號。
     *
     * @return list<list<string|null>>
     */
    private function parseValues(string $values): array
    {
        $rows = [];
        $row = [];
        $field = '';
        $quoted = false;
        $inString = false;
        $inRow = false;
        $length = strlen($values);

        for ($i = 0; $i < $length; $i++) {
            $char = $values[$i];

            if ($inString) {
                if ($char === '\\') {
                    $field .= $this->unescape($values[$i + 1] ?? '');
                    $i++;
                } elseif ($char === "'") {
                    $inString = false;
                } else {
                    $field .= $char;
                }

                continue;
            }

            if (! $inRow) {
                if ($char === '(') {
                    $inRow = true;
                    $row = [];
                    $field = '';
                    $quoted = false;
                }

                continue;
            }

            if ($char === "'") {
                $inString = true;
                $quoted = true;
            } elseif ($char === ',') {
                $row[] = $this->finishField($field, $quoted);
                $field = '';
                $quoted = false;
            } elseif ($char === ')') {
                $row[] = $this->finishField($field, $quoted);
                $rows[] = $row;
                $inRow = false;
            } else {
                $field .= $char;
            }
        }

        return $rows;
    }

    private function finishField(string $raw, bool $quoted): ?string
    {
        if ($quoted) {
            return $raw;
        }

        $trimmed = trim($raw);

        return strcasecmp($trimmed, 'NULL') === 0 ? null : $trimmed;
    }

    private function unescape(string $char): string
    {
        return match ($char) {
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            '0' => "\0",
            'b' => "\x08",
            'Z' => "\x1a",
            default => $char,
        };
    }
}
