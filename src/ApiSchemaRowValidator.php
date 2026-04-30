<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

use PhpSoftBox\Request\ApiSchema;
use PhpSoftBox\Validator\ValidationResult;

/**
 * @template T of ApiSchema
 */
final readonly class ApiSchemaRowValidator implements RowValidatorInterface
{
    /**
     * @param class-string<T> $schemaClass
     */
    public function __construct(
        private string $schemaClass,
    ) {
    }

    public function validate(array $row): ValidationResult
    {
        /** @var T $schema */
        $schema = new $this->schemaClass($row);

        return $schema->validationResult();
    }
}
