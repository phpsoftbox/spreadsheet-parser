<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

use InvalidArgumentException;

use function is_file;
use function trim;

final readonly class ImportSource
{
    private function __construct(
        public ?string $path,
        public ?string $content,
        public ?string $fileName,
    ) {
    }

    public static function fromPath(string $path, ?string $fileName = null): self
    {
        $resolvedPath = trim($path);
        if ($resolvedPath === '') {
            throw new InvalidArgumentException('ImportSource path cannot be empty.');
        }

        if (!is_file($resolvedPath)) {
            throw new InvalidArgumentException('ImportSource path must point to existing file.');
        }

        return new self(path: $resolvedPath, content: null, fileName: $fileName);
    }

    public static function fromContent(string $content, ?string $fileName = null): self
    {
        if ($content === '') {
            throw new InvalidArgumentException('ImportSource content cannot be empty.');
        }

        return new self(path: null, content: $content, fileName: $fileName);
    }
}
