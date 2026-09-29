<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser\Tests;

use PhpSoftBox\SpreadsheetParser\Csv\CsvReader;
use PhpSoftBox\SpreadsheetParser\Excel\XlsxReader;
use PhpSoftBox\SpreadsheetParser\ImportDriver;
use PhpSoftBox\SpreadsheetParser\ImportOptions;
use PhpSoftBox\SpreadsheetParser\ImportSource;
use PhpSoftBox\SpreadsheetParser\SpreadsheetParser;
use PhpSoftBox\SpreadsheetParser\Tests\Support\LineNumberImportDefinition;
use PhpSoftBox\SpreadsheetParser\Tests\Support\XlsxTestFileFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function memory_get_peak_usage;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(SpreadsheetParser::class)]
#[CoversClass(XlsxReader::class)]
#[CoversClass(CsvReader::class)]
#[CoversMethod(SpreadsheetParser::class, 'parse')]
final class SpreadsheetParserLimitsTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /**
     * Проверим, что ячейки в последней колонке Excel (XFD) не разворачивают строки в 16 тысяч ячеек, а дают ошибку
     * лимита колонок в строке: раньше каждая строка заполнялась null до этой колонки и файл в несколько КБ съедал
     * десятки мегабайт.
     *
     * @see SpreadsheetParser::parse()
     * @see XlsxReader::read()
     */
    #[Test]
    public function xlsxCellInLastExcelColumnIsRowColumnLimitError(): void
    {
        $file = $this->xlsx('<row r="1"><c r="A1" t="inlineStr"><is><t>name</t></is></c></row>'
            . str_repeat('<row><c r="XFD1"><v>1</v></c></row>', 200));

        $memoryBefore = memory_get_peak_usage();
        $result       = new SpreadsheetParser()->parse(
            ImportSource::fromPath($file),
            new LineNumberImportDefinition(ImportDriver::XLSX),
        );

        self::assertSame([], $result->rows);
        self::assertSame(2, $result->rowErrors[0]->rowNumber);
        self::assertSame(['row' => ['Превышен лимит количества колонок в строке.']], $result->rowErrors[0]->errors);
        self::assertCount(200, $result->rowErrors);
        self::assertLessThan(8_388_608, memory_get_peak_usage() - $memoryBefore);
    }

    /**
     * Проверим, что ссылка на ячейку дальше последней колонки Excel считается повреждённым файлом.
     *
     * @see SpreadsheetParser::parse()
     * @see XlsxReader::read()
     */
    #[Test]
    public function xlsxCellBeyondLastExcelColumnIsParsingError(): void
    {
        $file = $this->xlsx('<row r="1"><c r="XFE1" t="inlineStr"><is><t>name</t></is></c></row>');

        $result = new SpreadsheetParser()->parse(
            ImportSource::fromPath($file),
            new LineNumberImportDefinition(ImportDriver::XLSX),
        );

        self::assertSame(
            ['Ошибка парсинга файла: Worksheet cell is beyond the last Excel column.'],
            $result->globalErrors,
        );
    }

    /**
     * Проверим, что распакованный размер XML-части XLSX ограничен maxUncompressedBytes (защита от zip-бомбы):
     * сжатый файл мал, а лист после распаковки превышает лимит.
     *
     * @see SpreadsheetParser::parse()
     * @see XlsxReader::read()
     */
    #[Test]
    public function xlsxPartLargerThanUncompressedLimitIsRejected(): void
    {
        $file = $this->xlsx('<row r="1"><c r="A1" t="inlineStr"><is><t>name</t></is></c></row>'
            . str_repeat('<row><c t="inlineStr"><is><t>x</t></is></c></row>', 1_000));

        $result = new SpreadsheetParser()->parse(
            ImportSource::fromPath($file),
            new LineNumberImportDefinition(ImportDriver::XLSX, new ImportOptions(maxUncompressedBytes: 10_000)),
        );

        self::assertSame(
            ['Ошибка парсинга файла: Превышен лимит распакованного размера XLSX.'],
            $result->globalErrors,
        );
    }

    /**
     * Проверим, что номер строки XLSX берётся из атрибута r: Excel не пишет пустые строки, и порядковый номер
     * элемента row дал бы неверный номер строки в ошибках.
     *
     * @see SpreadsheetParser::parse()
     * @see XlsxReader::read()
     */
    #[Test]
    public function xlsxLineNumberComesFromRowReference(): void
    {
        $file = $this->xlsx('<row r="1"><c r="A1" t="inlineStr"><is><t>name</t></is></c></row>'
            . '<row r="5"><c r="A5" t="inlineStr"><is><t>Ivan</t></is></c></row>');

        $result = new SpreadsheetParser()->parse(
            ImportSource::fromPath($file),
            new LineNumberImportDefinition(ImportDriver::XLSX),
        );

        self::assertSame([['name' => 'Ivan', '_line' => 5]], $result->rows);
    }

    /**
     * Проверим, что пустые строки CSV пропускаются без сдвига номеров строк: читатель их не хранит, но номер
     * следующей строки остаётся номером записи в файле.
     *
     * @see SpreadsheetParser::parse()
     * @see CsvReader::read()
     */
    #[Test]
    public function csvEmptyLinesKeepLineNumbers(): void
    {
        $file = $this->csv("name\n\n\n\nIvan\n");

        $result = new SpreadsheetParser()->parse(
            ImportSource::fromPath($file),
            new LineNumberImportDefinition(ImportDriver::CSV),
        );

        self::assertSame([['name' => 'Ivan', '_line' => 5]], $result->rows);
    }

    /**
     * Проверим, что при чтении CSV с ограничением числа строк лимит по-прежнему срабатывает на первой лишней строке
     * данных, а в результат попадают только строки в пределах лимита.
     *
     * @see SpreadsheetParser::parse()
     * @see CsvReader::read()
     */
    #[Test]
    public function csvReadingStopsAfterRowLimit(): void
    {
        $file = $this->csv("name\n" . str_repeat("Ivan\n", 1_000));

        $result = new SpreadsheetParser()->parse(
            ImportSource::fromPath($file),
            new LineNumberImportDefinition(ImportDriver::CSV, new ImportOptions(maxRows: 2)),
        );

        self::assertCount(2, $result->rows);
        self::assertSame(3, $result->totalRows);
        self::assertSame(4, $result->rowErrors[0]->rowNumber);
    }

    private function xlsx(string $rowsXml): string
    {
        $file = XlsxTestFileFactory::createFromWorksheetXml(
            sheetName: 'Sheet1',
            worksheetXml: '<?xml version="1.0" encoding="UTF-8"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
                . $rowsXml
                . '</sheetData></worksheet>',
        );
        $this->files[] = $file;

        return $file;
    }

    private function csv(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'psb-import-limits-');
        self::assertIsString($file);
        file_put_contents($file, $content);
        $this->files[] = $file;

        return $file;
    }
}
