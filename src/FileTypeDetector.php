<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

use PhpSoftBox\SpreadsheetParser\Exception\UnsupportedFileTypeException;

use function array_key_exists;
use function finfo_file;
use function finfo_open;
use function in_array;
use function is_file;
use function is_string;
use function pathinfo;
use function strtolower;
use function trim;

use const FILEINFO_MIME_TYPE;
use const PATHINFO_EXTENSION;

final class FileTypeDetector
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_MIME_BY_EXTENSION = [
        'csv' => [
            'text/csv',
            'text/plain',
            'application/csv',
            'text/x-csv',
            'application/vnd.ms-excel',
        ],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/octet-stream',
        ],
        'xls' => [
            'application/vnd.ms-excel',
            'application/octet-stream',
        ],
    ];

    /**
     * @return array{type: ImportType, mime: string}
     */
    public function detect(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new UnsupportedFileTypeException('File does not exist.');
        }

        $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
        if (!array_key_exists($extension, self::ALLOWED_MIME_BY_EXTENSION)) {
            throw new UnsupportedFileTypeException('Unsupported file extension: ' . $extension);
        }

        $mime = $this->detectMime($filePath);
        if ($mime !== '' && !in_array($mime, self::ALLOWED_MIME_BY_EXTENSION[$extension], true)) {
            throw new UnsupportedFileTypeException(
                'MIME type does not match extension. Extension: ' . $extension . ', MIME: ' . $mime,
            );
        }

        return [
            'type' => ImportType::from($extension),
            'mime' => $mime,
        ];
    }

    private function detectMime(string $filePath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return '';
        }

        $mime = finfo_file($finfo, $filePath);

        if (!is_string($mime)) {
            return '';
        }

        return trim(strtolower($mime));
    }
}
