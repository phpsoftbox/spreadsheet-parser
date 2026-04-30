<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

use InvalidArgumentException;

final readonly class ImportOptions
{
    /**
     * @param list<string> $requiredColumns
     */
    public function __construct(
        public int $maxFileSizeBytes = 10_485_760,
        public int $maxRows = 10_000,
        public int $maxColumns = 200,
        public array $requiredColumns = [],
        public CsvOptions $csv = new CsvOptions(),
        public SheetSelection $sheet = new SheetSelection(0),
        public ?RowValidatorInterface $rowValidator = null,
    ) {
        if ($this->maxFileSizeBytes <= 0) {
            throw new InvalidArgumentException('maxFileSizeBytes must be greater than 0.');
        }

        if ($this->maxRows <= 0) {
            throw new InvalidArgumentException('maxRows must be greater than 0.');
        }

        if ($this->maxColumns <= 0) {
            throw new InvalidArgumentException('maxColumns must be greater than 0.');
        }
    }
}
