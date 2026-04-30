<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

use JsonException;
use PhpSoftBox\SpreadsheetParser\Csv\CsvWriter;
use PhpSoftBox\SpreadsheetParser\Excel\XlsxWriter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stringable;
use Throwable;

use function array_is_list;
use function array_keys;
use function array_values;
use function count;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function json_encode;
use function trim;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class SpreadsheetWriter
{
    public function __construct(
        private CsvWriter $csvWriter = new CsvWriter(),
        private XlsxWriter $xlsxWriter = new XlsxWriter(),
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param list<array<string, mixed>|list<mixed>> $rows
     * @param list<string>|null $headers
     */
    public function write(
        ImportDriver $driver,
        array $rows,
        ?array $headers = null,
        string $sheetName = 'Sheet1',
    ): string {
        $resolvedHeaders = $headers ?? $this->inferHeaders($rows);
        $tableRows       = $this->buildTableRows($rows, $resolvedHeaders);

        $this->logger->info('Spreadsheet export started', [
            'driver'        => $driver->value,
            'headers_count' => count($resolvedHeaders),
            'rows_count'    => count($rows),
        ]);

        try {
            $content = match ($driver) {
                ImportDriver::CSV  => $this->csvWriter->write($tableRows),
                ImportDriver::XLSX => $this->xlsxWriter->write($tableRows, $sheetName),
            };
        } catch (Throwable $exception) {
            $this->logger->error('Spreadsheet export failed', [
                'driver'            => $driver->value,
                'exception_class'   => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $this->logger->info('Spreadsheet export completed', [
            'driver'     => $driver->value,
            'rows_count' => count($rows),
        ]);

        return $content;
    }

    /**
     * @param list<array<string, mixed>|list<mixed>> $rows
     * @param list<string> $headers
     * @return list<list<scalar|null>>
     */
    private function buildTableRows(array $rows, array $headers): array
    {
        $tableRows = [];
        if ($headers !== []) {
            $tableRows[] = array_values($headers);
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (array_is_list($row)) {
                $resolvedRow = [];
                if ($headers === []) {
                    foreach ($row as $cell) {
                        $resolvedRow[] = $this->normalizeCellValue($cell);
                    }
                } else {
                    foreach ($headers as $index => $_header) {
                        $resolvedRow[] = $this->normalizeCellValue($row[$index] ?? null);
                    }
                }

                $tableRows[] = $resolvedRow;
                continue;
            }

            if ($headers === []) {
                $resolvedRow = [];
                foreach ($row as $cell) {
                    $resolvedRow[] = $this->normalizeCellValue($cell);
                }
                $tableRows[] = $resolvedRow;
                continue;
            }

            $resolvedRow = [];
            foreach ($headers as $header) {
                $resolvedRow[] = $this->normalizeCellValue($row[$header] ?? null);
            }
            $tableRows[] = $resolvedRow;
        }

        return $tableRows;
    }

    /**
     * @param list<array<string, mixed>|list<mixed>> $rows
     * @return list<string>
     */
    private function inferHeaders(array $rows): array
    {
        foreach ($rows as $row) {
            if (!is_array($row) || $row === []) {
                continue;
            }

            if (array_is_list($row)) {
                $headers = [];
                $total   = count($row);
                for ($index = 0; $index < $total; $index++) {
                    $headers[] = 'column_' . ($index + 1);
                }

                return $headers;
            }

            $headers = [];
            foreach (array_keys($row) as $key) {
                $resolved = trim((string) $key);
                if ($resolved === '') {
                    continue;
                }
                $headers[] = $resolved;
            }

            return $headers;
        }

        return [];
    }

    private function normalizeCellValue(mixed $value): string|int|float|bool|null
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_array($value) || is_object($value)) {
            try {
                return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } catch (JsonException) {
                return '';
            }
        }

        return (string) $value;
    }

}
