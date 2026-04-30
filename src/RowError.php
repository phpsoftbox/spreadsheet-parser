<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

final readonly class RowError
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(
        public int $rowNumber,
        public array $errors,
    ) {
    }
}
