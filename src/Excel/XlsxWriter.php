<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser\Excel;

use PhpSoftBox\SpreadsheetParser\Exception\ParsingException;
use ZipArchive;

use function chr;
use function file_get_contents;
use function htmlspecialchars;
use function implode;
use function intdiv;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function rename;
use function str_replace;
use function substr;
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use function unlink;

use const ENT_QUOTES;
use const ENT_XML1;

final class XlsxWriter
{
    /**
     * @param list<list<scalar|null>> $rows
     */
    public function write(array $rows, string $sheetName = 'Sheet1'): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'psb-export-');
        if (!is_string($tmpPath)) {
            throw new ParsingException('Cannot create temporary XLSX path.');
        }

        $path = $tmpPath . '.xlsx';
        rename($tmpPath, $path);

        try {
            $this->writeToPath($path, $rows, $sheetName);

            $content = file_get_contents($path);
            if (!is_string($content) || $content === '') {
                throw new ParsingException('Cannot read generated XLSX content.');
            }

            return $content;
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param list<list<scalar|null>> $rows
     */
    private function writeToPath(string $path, array $rows, string $sheetName): void
    {
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new ParsingException('Cannot create XLSX export archive.');
        }

        try {
            $sheetXml          = $this->worksheetXml($rows);
            $resolvedSheetName = $this->normalizeSheetName($sheetName);

            $workbookXml = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
                . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="' . $this->xml($resolvedSheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
                . '</workbook>';

            $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '</Relationships>';

            $rootRelsXml = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>';

            $contentTypesXml = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '</Types>';

            $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
            $zip->addFromString('xl/workbook.xml', $workbookXml);
            $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
            $zip->addFromString('_rels/.rels', $rootRelsXml);
            $zip->addFromString('[Content_Types].xml', $contentTypesXml);
        } finally {
            $zip->close();
        }
    }

    /**
     * @param list<list<scalar|null>> $rows
     */
    private function worksheetXml(array $rows): string
    {
        $rowNodes = [];
        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $cellsXml  = '';

            foreach ($row as $colIndex => $cell) {
                if ($cell === null) {
                    continue;
                }

                $ref = $this->columnLetter($colIndex) . $rowNumber;
                $cellsXml .= $this->cellXml($ref, $cell);
            }

            $rowNodes[] = '<row r="' . $rowNumber . '">' . $cellsXml . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . implode('', $rowNodes) . '</sheetData>'
            . '</worksheet>';
    }

    private function cellXml(string $ref, string|int|float|bool $cell): string
    {
        if (is_bool($cell)) {
            return '<c r="' . $ref . '" t="b"><v>' . ($cell ? '1' : '0') . '</v></c>';
        }

        if (is_int($cell) || is_float($cell)) {
            return '<c r="' . $ref . '"><v>' . (string) $cell . '</v></c>';
        }

        return '<c r="' . $ref . '" t="inlineStr"><is><t>'
            . $this->xml((string) $cell)
            . '</t></is></c>';
    }

    private function columnLetter(int $index): string
    {
        $number = $index + 1;
        $result = '';
        while ($number > 0) {
            $mod    = ($number - 1) % 26;
            $result = chr(65 + $mod) . $result;
            $number = intdiv($number - 1, 26);
        }

        return $result;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1);
    }

    private function normalizeSheetName(string $sheetName): string
    {
        $trimmed = trim($sheetName);
        if ($trimmed === '') {
            return 'Sheet1';
        }

        $cleaned = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $trimmed);
        $cleaned = trim($cleaned);
        if ($cleaned === '') {
            return 'Sheet1';
        }

        return substr($cleaned, 0, 31);
    }
}
