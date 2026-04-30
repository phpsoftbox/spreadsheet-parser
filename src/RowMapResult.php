<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

final readonly class RowMapResult
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, list<string>> $errors
     */
    private function __construct(
        public bool $isValid,
        public array $data,
        public array $errors,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function ok(array $data): self
    {
        return new self(isValid: true, data: $data, errors: []);
    }

    public static function error(string $message, string $field = 'row'): self
    {
        return new self(
            isValid: false,
            data: [],
            errors: [$field => [$message]],
        );
    }

    /**
     * @param array<string, list<string>> $errors
     */
    public static function errors(array $errors): self
    {
        return new self(
            isValid: false,
            data: [],
            errors: $errors,
        );
    }
}
