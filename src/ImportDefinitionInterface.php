<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

interface ImportDefinitionInterface
{
    public function driver(): ImportDriver;

    public function options(): ImportOptions;

    public function allowHeaderless(): bool;

    /**
     * @param array<string, mixed> $row
     */
    public function mapRow(array $row, int $lineNumber): RowMapResult;
}
