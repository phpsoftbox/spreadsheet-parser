<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

use InvalidArgumentException;
use JsonException;
use Stringable;

use function is_bool;
use function is_float;
use function is_int;
use function is_scalar;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class SpreadsheetCell
{
    public function __construct(
        public string|int|float|bool|null $value,
        public SpreadsheetCellType $type = SpreadsheetCellType::Auto,
    ) {
        if (
            $this->type === SpreadsheetCellType::Number
            && $this->value !== null
            && !is_int($this->value)
            && !is_float($this->value)
        ) {
            throw new InvalidArgumentException('Spreadsheet number cell value must be int, float or null.');
        }

        if (
            $this->type === SpreadsheetCellType::Boolean
            && $this->value !== null
            && !is_bool($this->value)
        ) {
            throw new InvalidArgumentException('Spreadsheet boolean cell value must be bool or null.');
        }
    }

    public static function text(mixed $value): self
    {
        return new self(self::normalizeTextValue($value), SpreadsheetCellType::Text);
    }

    public static function number(int|float|null $value): self
    {
        return new self($value, SpreadsheetCellType::Number);
    }

    public static function boolean(?bool $value): self
    {
        return new self($value, SpreadsheetCellType::Boolean);
    }

    public static function auto(string|int|float|bool|null $value): self
    {
        return new self($value);
    }

    private static function normalizeTextValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return '';
        }
    }
}
