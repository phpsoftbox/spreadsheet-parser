<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

use PhpSoftBox\Validator\ValidationResult;

interface RowValidatorInterface
{
    /**
     * @param array<string, mixed> $row
     */
    public function validate(array $row): ValidationResult;
}
