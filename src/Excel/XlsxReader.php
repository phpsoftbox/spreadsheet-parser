<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser\Excel;

use DateTimeImmutable;
use DateTimeZone;
use PhpSoftBox\SpreadsheetParser\Exception\ParsingException;
use PhpSoftBox\SpreadsheetParser\Internal\RawTable;
use PhpSoftBox\SpreadsheetParser\SheetSelection;
use SimpleXMLElement;
use XMLReader;
use ZipArchive;

use function array_key_exists;
use function array_keys;
use function floor;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function ksort;
use function ltrim;
use function max;
use function ord;
use function preg_match;
use function preg_replace;
use function round;
use function simplexml_load_string;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strtolower;
use function trim;

use const LIBXML_NOCDATA;
use const LIBXML_NONET;

final class XlsxReader
{
    /**
     * @var list<int>
     */
    private const BUILTIN_DATE_FORMATS = [
        14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 30, 36, 45, 46, 47, 50, 57,
    ];

    public function read(string $filePath, SheetSelection $selection): RawTable
    {
        $zip = new ZipArchive();

        if ($zip->open($filePath) !== true) {
            throw new ParsingException('Cannot open XLSX file.');
        }

        try {
            $workbookXml = $zip->getFromName('xl/workbook.xml');
            $relsXml     = $zip->getFromName('xl/_rels/workbook.xml.rels');
            if (!is_string($workbookXml) || !is_string($relsXml)) {
                throw new ParsingException('XLSX workbook metadata is missing.');
            }

            $sheetMeta       = $this->parseWorkbookSheets($workbookXml);
            $relationshipMap = $this->parseWorkbookRelationships($relsXml);
            $sheetPath       = $this->resolveSheetPath($sheetMeta, $relationshipMap, $selection);

            $sheetXml = $zip->getFromName($sheetPath);
            if (!is_string($sheetXml)) {
                throw new ParsingException('Selected worksheet XML not found.');
            }

            $sharedStrings = [];
            $sharedXml     = $zip->getFromName('xl/sharedStrings.xml');
            if (is_string($sharedXml)) {
                $sharedStrings = $this->parseSharedStrings($sharedXml);
            }

            $styleIsDate = [];
            $stylesXml   = $zip->getFromName('xl/styles.xml');
            if (is_string($stylesXml)) {
                $styleIsDate = $this->parseStyleDateMap($stylesXml);
            }

            return new RawTable($this->readSheetRows($sheetXml, $sharedStrings, $styleIsDate));
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<array{name: string, rid: string}>
     */
    private function parseWorkbookSheets(string $xml): array
    {
        $root = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if (!$root instanceof SimpleXMLElement) {
            throw new ParsingException('Invalid workbook.xml.');
        }

        $nodes = $root->xpath('/*[local-name()="workbook"]/*[local-name()="sheets"]/*[local-name()="sheet"]');
        if (!is_array($nodes)) {
            throw new ParsingException('Workbook does not contain sheet definitions.');
        }

        $sheets = [];
        foreach ($nodes as $sheet) {
            $attributes  = $sheet->attributes();
            $relAttrs    = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $sheetName   = trim((string) ($attributes?->name ?? ''));
            $relationRef = trim((string) ($relAttrs?->id ?? ''));

            if ($sheetName === '' || $relationRef === '') {
                continue;
            }

            $sheets[] = ['name' => $sheetName, 'rid' => $relationRef];
        }

        if ($sheets === []) {
            throw new ParsingException('Workbook does not contain sheets.');
        }

        return $sheets;
    }

    /**
     * @return array<string, string>
     */
    private function parseWorkbookRelationships(string $xml): array
    {
        $root = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if (!$root instanceof SimpleXMLElement) {
            throw new ParsingException('Invalid workbook relationships.');
        }

        $nodes = $root->xpath('/*[local-name()="Relationships"]/*[local-name()="Relationship"]');
        if (!is_array($nodes)) {
            return [];
        }

        $map = [];
        foreach ($nodes as $relation) {
            $attributes = $relation->attributes();
            $id         = trim((string) ($attributes?->Id ?? ''));
            $target     = trim((string) ($attributes?->Target ?? ''));
            if ($id === '' || $target === '') {
                continue;
            }

            $target = ltrim($target, '/');
            if (!str_starts_with($target, 'xl/')) {
                $target = 'xl/' . $target;
            }

            $map[$id] = $target;
        }

        return $map;
    }

    /**
     * @param list<array{name: string, rid: string}> $sheets
     * @param array<string, string> $relationships
     */
    private function resolveSheetPath(array $sheets, array $relationships, SheetSelection $selection): string
    {
        $sheet = null;
        if ($selection->name !== null) {
            foreach ($sheets as $item) {
                if ($item['name'] === $selection->name) {
                    $sheet = $item;
                    break;
                }
            }

            if ($sheet === null) {
                throw new ParsingException('Worksheet not found: ' . $selection->name);
            }
        } else {
            $index = $selection->index ?? 0;
            if (!array_key_exists($index, $sheets)) {
                throw new ParsingException('Worksheet index is out of range: ' . $index);
            }
            $sheet = $sheets[$index];
        }

        $rid = $sheet['rid'];
        if (!array_key_exists($rid, $relationships)) {
            throw new ParsingException('Worksheet relationship not found: ' . $rid);
        }

        return $relationships[$rid];
    }

    /**
     * @return list<string>
     */
    private function parseSharedStrings(string $xml): array
    {
        $root = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if (!$root instanceof SimpleXMLElement) {
            return [];
        }

        $nodes = $root->xpath('/*[local-name()="sst"]/*[local-name()="si"]');
        if (!is_array($nodes)) {
            return [];
        }

        $values = [];
        foreach ($nodes as $item) {
            $textNodes = $item->xpath('./*[local-name()="t"]');
            if (is_array($textNodes) && $textNodes !== []) {
                $values[] = (string) $textNodes[0];
                continue;
            }

            $parts = [];
            $runs  = $item->xpath('./*[local-name()="r"]');
            foreach ($runs ?: [] as $run) {
                $runText = $run->xpath('./*[local-name()="t"]');
                $parts[] = is_array($runText) && $runText !== [] ? (string) $runText[0] : '';
            }

            $values[] = $parts === [] ? '' : implode('', $parts);
        }

        return $values;
    }

    /**
     * @return array<int, bool>
     */
    private function parseStyleDateMap(string $xml): array
    {
        $root = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if (!$root instanceof SimpleXMLElement) {
            return [];
        }

        $customFormats = [];
        $numFmtNodes   = $root->xpath('//*[local-name()="numFmts"]/*[local-name()="numFmt"]');
        foreach ($numFmtNodes ?: [] as $numFmt) {
            $attributes = $numFmt->attributes();
            $id         = (int) ($attributes?->numFmtId ?? -1);
            $code       = (string) ($attributes?->formatCode ?? '');
            if ($id >= 0 && $code !== '') {
                $customFormats[$id] = $code;
            }
        }

        $styleMap = [];
        $index    = 0;
        $xfNodes  = $root->xpath('//*[local-name()="cellXfs"]/*[local-name()="xf"]');
        foreach ($xfNodes ?: [] as $xf) {
            $attributes       = $xf->attributes();
            $numFmtId         = (int) ($attributes?->numFmtId ?? 0);
            $styleMap[$index] = in_array($numFmtId, self::BUILTIN_DATE_FORMATS, true)
                || ($customFormats[$numFmtId] ?? null) !== null
                    && $this->isDateFormatCode($customFormats[$numFmtId]);
            $index++;
        }

        return $styleMap;
    }

    private function isDateFormatCode(string $formatCode): bool
    {
        $clean = preg_replace('/"(.*?)"/', '', $formatCode) ?? $formatCode;
        $clean = preg_replace('/\[(.*?)\]/', '', $clean) ?? $clean;
        $clean = strtolower($clean);

        return str_contains($clean, 'yy')
            || str_contains($clean, 'dd')
            || str_contains($clean, 'mm')
            || str_contains($clean, 'hh')
            || str_contains($clean, 'ss');
    }

    /**
     * @param list<string> $sharedStrings
     * @param array<int, bool> $styleIsDate
     * @return list<list<mixed>>
     */
    private function readSheetRows(string $sheetXml, array $sharedStrings, array $styleIsDate): array
    {
        $reader = new XMLReader();

        if (!$reader->XML($sheetXml, null, LIBXML_NONET | LIBXML_NOCDATA)) {
            throw new ParsingException('Invalid worksheet XML.');
        }

        $rows = [];
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }

            $rowXml = $reader->readOuterXML();
            $row    = $this->parseRow($rowXml, $sharedStrings, $styleIsDate);
            $rows[] = $row;
        }

        $reader->close();

        return $rows;
    }

    /**
     * @param list<string> $sharedStrings
     * @param array<int, bool> $styleIsDate
     * @return list<mixed>
     */
    private function parseRow(string $rowXml, array $sharedStrings, array $styleIsDate): array
    {
        $rowNode = simplexml_load_string($rowXml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if (!$rowNode instanceof SimpleXMLElement) {
            throw new ParsingException('Invalid worksheet row XML.');
        }

        $mapped = [];
        $cells  = $rowNode->xpath('./*[local-name()="c"]');
        foreach ($cells ?: [] as $cell) {
            $attributes = $cell->attributes();
            $ref        = (string) ($attributes?->r ?? '');
            $type       = (string) ($attributes?->t ?? '');
            $styleIndex = (int) ($attributes?->s ?? 0);
            $index      = $this->columnIndexFromReference($ref);

            $mapped[$index] = $this->parseCellValue(
                cell: $cell,
                type: $type,
                styleIndex: $styleIndex,
                sharedStrings: $sharedStrings,
                styleIsDate: $styleIsDate,
            );
        }

        if ($mapped === []) {
            return [];
        }

        ksort($mapped);
        $lastIndex = (int) max(array_keys($mapped));
        $result    = [];
        for ($i = 0; $i <= $lastIndex; $i++) {
            $result[] = $mapped[$i] ?? null;
        }

        return $result;
    }

    /**
     * @param list<string> $sharedStrings
     * @param array<int, bool> $styleIsDate
     */
    private function parseCellValue(
        SimpleXMLElement $cell,
        string $type,
        int $styleIndex,
        array $sharedStrings,
        array $styleIsDate,
    ): mixed {
        if ($type === 'inlineStr') {
            $textNodes = $cell->xpath('./*[local-name()="is"]/*[local-name()="t"]');
            if (is_array($textNodes) && $textNodes !== []) {
                return (string) $textNodes[0];
            }

            $parts    = [];
            $runNodes = $cell->xpath('./*[local-name()="is"]/*[local-name()="r"]');
            foreach ($runNodes ?: [] as $run) {
                $runText = $run->xpath('./*[local-name()="t"]');
                $parts[] = is_array($runText) && $runText !== [] ? (string) $runText[0] : '';
            }

            return $parts === [] ? '' : implode('', $parts);
        }

        $values = $cell->xpath('./*[local-name()="v"]');
        $raw    = is_array($values) && $values !== [] ? (string) $values[0] : null;
        if ($raw === null) {
            return null;
        }

        if ($type === 's') {
            $sharedIndex = (int) $raw;

            return $sharedStrings[$sharedIndex] ?? '';
        }

        if ($type === 'b') {
            return $raw === '1';
        }

        if (!is_numeric($raw)) {
            return $raw;
        }

        if (($styleIsDate[$styleIndex] ?? false) === true) {
            return $this->convertExcelDate((float) $raw);
        }

        if (preg_match('/^-?\d+$/', $raw) === 1) {
            return (int) $raw;
        }

        return (float) $raw;
    }

    private function columnIndexFromReference(string $reference): int
    {
        if ($reference === '') {
            return 0;
        }

        if (preg_match('/^([A-Z]+)\d+$/', $reference, $matches) !== 1) {
            return 0;
        }

        $letters = $matches[1];
        $index   = 0;
        $length  = strlen($letters);
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    private function convertExcelDate(float $serial): string
    {
        $days         = (int) floor($serial);
        $fraction     = $serial - $days;
        $secondsOfDay = (int) round($fraction * 86_400);
        $adjustedDays = $days > 59 ? $days - 1 : $days;
        $baseDate     = new DateTimeImmutable('1899-12-31 00:00:00', new DateTimeZone('UTC'));

        $dateWithDays = $baseDate->modify(sprintf('+%d days', $adjustedDays));
        $dateWithTime = $secondsOfDay > 0 ? $dateWithDays->modify(sprintf('+%d seconds', $secondsOfDay)) : $dateWithDays;

        if ($secondsOfDay === 0) {
            return $dateWithTime->format('Y-m-d');
        }

        return $dateWithTime->format('Y-m-d H:i:s');
    }
}
