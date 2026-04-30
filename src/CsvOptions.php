<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

use InvalidArgumentException;

use function in_array;
use function mb_strtolower;
use function strlen;

final readonly class CsvOptions
{
    /**
     * @param list<string>|null $delimiters
     */
    public function __construct(
        public ?array $delimiters = null,
        public string $enclosure = '"',
        public string $escape = '\\',
        public string $encoding = 'auto',
    ) {
        if ($this->delimiters !== null) {
            foreach ($this->delimiters as $delimiter) {
                if (strlen($delimiter) !== 1) {
                    throw new InvalidArgumentException('Each CSV delimiter must be one character.');
                }
            }
        }

        if (strlen($this->enclosure) !== 1) {
            throw new InvalidArgumentException('CSV enclosure must be one character.');
        }

        if (strlen($this->escape) !== 1) {
            throw new InvalidArgumentException('CSV escape must be one character.');
        }

        if (!in_array(mb_strtolower($this->encoding), ['auto', 'utf-8', 'windows-1251'], true)) {
            throw new InvalidArgumentException('CSV encoding must be auto, UTF-8 or Windows-1251.');
        }
    }
}
