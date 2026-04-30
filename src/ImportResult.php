<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

final readonly class ImportResult
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $headers
     * @param list<RowError> $rowErrors
     * @param list<string> $globalErrors
     */
    public function __construct(
        public ImportType $fileType,
        public array $rows,
        public array $headers,
        public array $rowErrors,
        public array $globalErrors,
        public int $totalRows,
    ) {
    }

    public function hasErrors(): bool
    {
        return $this->rowErrors !== [] || $this->globalErrors !== [];
    }
}
