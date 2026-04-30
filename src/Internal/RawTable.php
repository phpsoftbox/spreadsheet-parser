<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser\Internal;

final readonly class RawTable
{
    /**
     * @param list<list<mixed>> $rows
     */
    public function __construct(
        public array $rows,
    ) {
    }
}
