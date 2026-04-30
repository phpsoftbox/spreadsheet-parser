<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser\Tests\Support;

use RuntimeException;
use ZipArchive;

use function array_key_exists;
use function chr;
use function htmlspecialchars;
use function implode;
use function intdiv;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function rename;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;

use const ENT_QUOTES;
use const ENT_XML1;

final class XlsxTestFileFactory
{
    /**
     * @param list<array{name: string, rows: list<list<mixed>>}> $sheets
     */
    public static function create(array $sheets, ?string $stylesXml = null): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'psb-import-');
        if (!is_string($tmpPath)) {
            throw new RuntimeException('Cannot create temporary XLSX path.');
        }

        $path = $tmpPath . '.xlsx';
        rename($tmpPath, $path);

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create XLSX fixture.');
        }

        $sheetDefinitions = [];
        $relationshipRows = [];
        $contentTypes     = [];

        foreach ($sheets as $index => $sheet) {
            $sheetId   = $index + 1;
            $rid       = 'rId' . $sheetId;
            $sheetXml  = self::worksheetXml($sheet['rows']);
            $sheetPath = 'xl/worksheets/sheet' . $sheetId . '.xml';

            $zip->addFromString($sheetPath, $sheetXml);

            $sheetDefinitions[] = sprintf(
                '<sheet name="%s" sheetId="%d" r:id="%s"/>',
                self::xml($sheet['name']),
                $sheetId,
                $rid,
            );
            $relationshipRows[] = sprintf(
                '<Relationship Id="%s" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet%d.xml"/>',
                $rid,
                $sheetId,
            );
            $contentTypes[] = sprintf(
                '<Override PartName="/xl/worksheets/sheet%d.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>',
                $sheetId,
            );
        }

        $workbookXml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . implode('', $sheetDefinitions) . '</sheets></workbook>';

        $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . implode('', $relationshipRows)
            . '</Relationships>';

        $rootRelsXml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $stylesContentType = '';
        if ($stylesXml !== null) {
            $zip->addFromString('xl/styles.xml', $stylesXml);
            $stylesContentType = '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        }

        $contentTypesXml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . implode('', $contentTypes)
            . $stylesContentType
            . '</Types>';

        $zip->addFromString('xl/workbook.xml', $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
        $zip->addFromString('_rels/.rels', $rootRelsXml);
        $zip->addFromString('[Content_Types].xml', $contentTypesXml);
        $zip->close();

        return $path;
    }

    /**
     * @param list<list<mixed>> $rows
     */
    private static function worksheetXml(array $rows): string
    {
        $rowNodes = [];
        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $cellsXml  = '';

            foreach ($row as $colIndex => $cell) {
                if ($cell === null) {
                    continue;
                }

                $ref = self::columnLetter($colIndex) . $rowNumber;
                $cellsXml .= self::cellXml($ref, $cell);
            }

            $rowNodes[] = sprintf('<row r="%d">%s</row>', $rowNumber, $cellsXml);
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . implode('', $rowNodes) . '</sheetData>'
            . '</worksheet>';
    }

    private static function cellXml(string $ref, mixed $cell): string
    {
        if (is_array($cell)) {
            $style = array_key_exists('style', $cell) ? ' s="' . (int) $cell['style'] . '"' : '';
            if (array_key_exists('formula', $cell)) {
                $formula = self::xml((string) $cell['formula']);
                $value   = self::xml((string) ($cell['value'] ?? ''));

                return sprintf('<c r="%s"%s><f>%s</f><v>%s</v></c>', $ref, $style, $formula, $value);
            }

            if (array_key_exists('inline', $cell)) {
                $value = self::xml((string) $cell['inline']);

                return sprintf('<c r="%s"%s t="inlineStr"><is><t>%s</t></is></c>', $ref, $style, $value);
            }

            if (array_key_exists('value', $cell) && is_numeric($cell['value'])) {
                $value = self::xml((string) $cell['value']);

                return sprintf('<c r="%s"%s><v>%s</v></c>', $ref, $style, $value);
            }
        }

        if (is_bool($cell)) {
            return sprintf('<c r="%s" t="b"><v>%s</v></c>', $ref, $cell ? '1' : '0');
        }

        if (is_int($cell) || is_float($cell)) {
            return sprintf('<c r="%s"><v>%s</v></c>', $ref, (string) $cell);
        }

        $value = self::xml((string) $cell);

        return sprintf('<c r="%s" t="inlineStr"><is><t>%s</t></is></c>', $ref, $value);
    }

    private static function columnLetter(int $index): string
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

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1);
    }
}
