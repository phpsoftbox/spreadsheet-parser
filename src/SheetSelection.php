<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

use InvalidArgumentException;

final readonly class SheetSelection
{
    public function __construct(
        public ?int $index = null,
        public ?string $name = null,
    ) {
        if ($this->index !== null && $this->index < 0) {
            throw new InvalidArgumentException('Sheet index must be greater than or equal to 0.');
        }

        if ($this->index !== null && $this->name !== null) {
            throw new InvalidArgumentException('Sheet selection must have either index or name.');
        }
    }

    public static function first(): self
    {
        return new self(index: 0);
    }

    public static function byIndex(int $index): self
    {
        return new self(index: $index);
    }

    public static function byName(string $name): self
    {
        return new self(name: $name);
    }
}
