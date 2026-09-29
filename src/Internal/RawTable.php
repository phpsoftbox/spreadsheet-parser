<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser\Internal;

use function is_string;
use function trim;

final readonly class RawTable
{
    /**
     * @param array<int, array<int, mixed>> $rows Непустые строки по индексу строки файла (с нуля); ячейки — по
     *                                            индексу колонки, пропущенных ячеек в строке может не быть
     */
    public function __construct(
        public array $rows,
    ) {
    }

    /**
     * Строка пустая, если в ней нет ячеек, кроме null и строк из пробелов.
     *
     * @param array<int, mixed> $row
     */
    public static function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }

            return false;
        }

        return true;
    }
}
