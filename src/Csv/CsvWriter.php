<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser\Csv;

use PhpSoftBox\SpreadsheetParser\Exception\ParsingException;
use PhpSoftBox\SpreadsheetParser\SpreadsheetCell;
use PhpSoftBox\SpreadsheetParser\SpreadsheetCellType;

use function fclose;
use function fopen;
use function fwrite;
use function implode;
use function is_string;
use function rewind;
use function str_contains;
use function str_replace;
use function stream_get_contents;
use function strlen;
use function strpos;
use function substr;

final class CsvWriter
{
    /**
     * @param list<list<scalar|SpreadsheetCell|null>> $rows
     */
    public function write(
        array $rows,
        string $delimiter = ',',
        string $enclosure = '"',
        string $escape = '\\',
        bool $withUtf8Bom = true,
    ): string {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new ParsingException('Cannot open temporary stream for CSV export.');
        }

        try {
            if ($withUtf8Bom) {
                fwrite($stream, "\xEF\xBB\xBF");
            }

            foreach ($rows as $row) {
                fwrite($stream, $this->rowToCsvLine($row, $delimiter, $enclosure, $escape));
            }

            rewind($stream);
            $content = stream_get_contents($stream);
            if (!is_string($content)) {
                throw new ParsingException('Cannot read generated CSV content.');
            }

            return $content;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param list<scalar|SpreadsheetCell|null> $row
     */
    private function rowToCsvLine(array $row, string $delimiter, string $enclosure, string $escape): string
    {
        $cells = [];
        foreach ($row as $cell) {
            $cells[] = $this->cellToCsv($cell, $delimiter, $enclosure, $escape);
        }

        return implode($delimiter, $cells) . "\n";
    }

    private function cellToCsv(
        string|int|float|bool|SpreadsheetCell|null $cell,
        string $delimiter,
        string $enclosure,
        string $escape,
    ): string {
        $forceQuote = false;
        if ($cell instanceof SpreadsheetCell) {
            $forceQuote = $cell->type === SpreadsheetCellType::Text;
            $cell       = $cell->value;
        }

        if ($cell === null) {
            return '';
        }

        if (is_string($cell)) {
            $forceQuote = true;
            $value      = $cell;
        } elseif ($cell === true) {
            $value = '1';
        } elseif ($cell === false) {
            $value = '';
        } else {
            $value = (string) $cell;
        }

        if (
            $forceQuote
            || str_contains($value, $delimiter)
            || str_contains($value, $enclosure)
            || ($escape !== '' && str_contains($value, $escape))
            || str_contains($value, "\n")
            || str_contains($value, "\r")
        ) {
            return $enclosure . $this->escapeEnclosures($value, $enclosure, $escape) . $enclosure;
        }

        return $value;
    }

    private function escapeEnclosures(string $value, string $enclosure, string $escape): string
    {
        if ($escape === '') {
            return str_replace($enclosure, $enclosure . $enclosure, $value);
        }

        $escaped         = '';
        $offset          = 0;
        $enclosureLength = strlen($enclosure);
        $escapeLength    = strlen($escape);

        while (($position = strpos($value, $enclosure, $offset)) !== false) {
            $escaped .= substr($value, $offset, $position - $offset);

            $isAlreadyEscaped = $position >= $escapeLength
                && substr($value, $position - $escapeLength, $escapeLength) === $escape;

            $escaped .= $isAlreadyEscaped ? $enclosure : $enclosure . $enclosure;
            $offset = $position + $enclosureLength;
        }

        return $escaped . substr($value, $offset);
    }
}
