<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser\Tests\Support;

use PhpSoftBox\SpreadsheetParser\AbstractImportDefinition;
use PhpSoftBox\SpreadsheetParser\ImportDriver;
use PhpSoftBox\SpreadsheetParser\ImportOptions;
use PhpSoftBox\SpreadsheetParser\RowMapResult;

/**
 * Возвращает строку как есть и добавляет номер строки файла в ключ `_line`.
 */
final class LineNumberImportDefinition extends AbstractImportDefinition
{
    public function __construct(
        private readonly ImportDriver $driver,
        private readonly ImportOptions $options = new ImportOptions(),
    ) {
    }

    public function driver(): ImportDriver
    {
        return $this->driver;
    }

    public function options(): ImportOptions
    {
        return $this->options;
    }

    public function mapRow(array $row, int $lineNumber): RowMapResult
    {
        return RowMapResult::ok($row + ['_line' => $lineNumber]);
    }
}
