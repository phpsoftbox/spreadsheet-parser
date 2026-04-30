<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser\Csv;

use PhpSoftBox\SpreadsheetParser\Exception\ParsingException;

use function fclose;
use function fopen;
use function fputcsv;
use function fwrite;
use function is_string;
use function rewind;
use function stream_get_contents;

final class CsvWriter
{
    /**
     * @param list<list<scalar|null>> $rows
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
                fputcsv($stream, $row, $delimiter, $enclosure, $escape);
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
}
