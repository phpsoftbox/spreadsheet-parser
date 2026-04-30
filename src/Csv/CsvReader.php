<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser\Csv;

use PhpSoftBox\SpreadsheetParser\CsvOptions;
use PhpSoftBox\SpreadsheetParser\Exception\ParsingException;
use PhpSoftBox\SpreadsheetParser\Internal\RawTable;
use SplFileObject;

use function count;
use function fclose;
use function feof;
use function fgets;
use function fopen;
use function fread;
use function in_array;
use function is_array;
use function is_string;
use function mb_check_encoding;
use function mb_convert_encoding;
use function mb_strtolower;
use function str_getcsv;
use function str_starts_with;
use function strlen;
use function substr;

final class CsvReader
{
    public function read(string $filePath, CsvOptions $options): RawTable
    {
        $encoding  = $this->resolveEncoding($filePath, $options);
        $delimiter = $this->resolveDelimiter($filePath, $options);

        $file = new SplFileObject($filePath, 'rb');

        $file->setFlags(SplFileObject::READ_CSV);
        $file->setCsvControl($delimiter, $options->enclosure, $options->escape);

        $rows      = [];
        $rowNumber = 0;
        while (!$file->eof()) {
            $row = $file->fgetcsv();
            if (!is_array($row)) {
                continue;
            }

            $rowNumber++;
            if ($row === [null] && $file->eof()) {
                continue;
            }

            $normalized = [];
            foreach ($row as $index => $value) {
                $cell = is_string($value) ? $value : '';
                if ($rowNumber === 1 && $index === 0) {
                    $cell = $this->removeUtf8Bom($cell);
                }
                $normalized[] = $this->convertEncoding($cell, $encoding);
            }

            $rows[] = $normalized;
        }

        return new RawTable($rows);
    }

    private function resolveDelimiter(string $filePath, CsvOptions $options): string
    {
        if ($options->delimiters !== null && count($options->delimiters) === 1) {
            return $options->delimiters[0];
        }

        $candidates = $options->delimiters ?? [',', ';', "\t"];
        if ($candidates === []) {
            throw new ParsingException('CSV delimiters list cannot be empty.');
        }

        $sample = $this->readSample($filePath, 30);
        if ($sample === []) {
            return ',';
        }

        $bestDelimiter = ',';
        $bestScore     = 0;

        foreach ($candidates as $candidate) {
            $score = 0;
            foreach ($sample as $line) {
                $cells = str_getcsv($line, $candidate, $options->enclosure, $options->escape);
                $score += count($cells);
            }

            if ($score > $bestScore) {
                $bestScore     = $score;
                $bestDelimiter = $candidate;
            }
        }

        return $bestDelimiter;
    }

    private function resolveEncoding(string $filePath, CsvOptions $options): string
    {
        $encoding = mb_strtolower($options->encoding);
        if ($encoding !== 'auto') {
            return $encoding;
        }

        $sample = $this->readRawSample($filePath);
        if ($sample === '') {
            return 'utf-8';
        }

        if (str_starts_with($sample, "\xEF\xBB\xBF")) {
            return 'utf-8';
        }

        if (mb_check_encoding($sample, 'UTF-8')) {
            return 'utf-8';
        }

        return 'windows-1251';
    }

    private function convertEncoding(string $value, string $encoding): string
    {
        if ($value === '') {
            return '';
        }

        if (in_array($encoding, ['utf-8', 'UTF-8'], true)) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', $encoding);
    }

    private function removeUtf8Bom(string $value): string
    {
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            return substr($value, 3);
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function readSample(string $filePath, int $maxLines): array
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new ParsingException('Cannot open CSV file.');
        }

        $lines = [];
        while (count($lines) < $maxLines) {
            $line = fgets($handle);
            if ($line === false) {
                break;
            }
            $lines[] = $line;
        }

        fclose($handle);

        return $lines;
    }

    private function readRawSample(string $filePath): string
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new ParsingException('Cannot open CSV file.');
        }

        $sample = '';
        $chunk  = 4096;
        $read   = 0;
        while ($read < 32_768 && !feof($handle)) {
            $buffer = fread($handle, $chunk);
            if (!is_string($buffer) || $buffer === '') {
                break;
            }
            $sample .= $buffer;
            $read += strlen($buffer);
        }

        fclose($handle);

        return $sample;
    }
}
