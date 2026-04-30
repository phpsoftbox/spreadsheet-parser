<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser\Tests;

use PhpSoftBox\SpreadsheetParser\AbstractImportDefinition;
use PhpSoftBox\SpreadsheetParser\ImportDriver;
use PhpSoftBox\SpreadsheetParser\ImportOptions;
use PhpSoftBox\SpreadsheetParser\ImportResult;
use PhpSoftBox\SpreadsheetParser\ImportSource;
use PhpSoftBox\SpreadsheetParser\RowMapResult;
use PhpSoftBox\SpreadsheetParser\SheetSelection;
use PhpSoftBox\SpreadsheetParser\SpreadsheetParser;
use PhpSoftBox\SpreadsheetParser\SpreadsheetWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_string;
use function rename;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(SpreadsheetWriter::class)]
final class SpreadsheetWriterTest extends TestCase
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
    }

    #[Test]
    /**
     * Проверяет экспорт XLSX и обратное чтение через SpreadsheetParser.
     */
    public function exportsXlsxAndCanBeParsedBack(): void
    {
        $writer = new SpreadsheetWriter();

        $content = $writer->write(
            driver: ImportDriver::XLSX,
            headers: ['id', 'name', 'barcode'],
            rows: [
                ['id' => 1, 'name' => 'Товар 1', 'barcode' => '4601234567890'],
                ['id' => 2, 'name' => 'Товар 2', 'barcode' => '4601234567891'],
            ],
            sheetName: 'Products',
        );

        $path   = $this->tempFile('.xlsx', $content);
        $result = $this->parseIdentity(
            filePath: $path,
            driver: ImportDriver::XLSX,
            options: new ImportOptions(sheet: SheetSelection::byName('Products')),
        );

        self::assertSame(['id', 'name', 'barcode'], $result->headers);
        self::assertCount(2, $result->rows);
        self::assertSame('Товар 1', $result->rows[0]['name']);
        self::assertSame('4601234567891', $result->rows[1]['barcode']);
    }

    #[Test]
    /**
     * Проверяет экспорт CSV и обратное чтение через SpreadsheetParser.
     */
    public function exportsCsvAndCanBeParsedBack(): void
    {
        $writer = new SpreadsheetWriter();

        $content = $writer->write(
            driver: ImportDriver::CSV,
            headers: ['id', 'name'],
            rows: [
                ['id' => 10, 'name' => 'Alpha'],
                ['id' => 11, 'name' => 'Beta'],
            ],
        );

        $path   = $this->tempFile('.csv', $content);
        $result = $this->parseIdentity($path, ImportDriver::CSV);

        self::assertSame(['id', 'name'], $result->headers);
        self::assertCount(2, $result->rows);
        self::assertSame('10', $result->rows[0]['id']);
        self::assertSame('Beta', $result->rows[1]['name']);
    }

    #[Test]
    /**
     * Проверяет автоопределение заголовков из ассоциативных строк.
     */
    public function infersHeadersWhenTheyAreNotProvided(): void
    {
        $writer = new SpreadsheetWriter();

        $content = $writer->write(
            driver: ImportDriver::XLSX,
            rows: [
                ['sku' => 'A-001', 'qty' => 2],
                ['sku' => 'A-002', 'qty' => 5],
            ],
        );

        $path   = $this->tempFile('.xlsx', $content);
        $result = $this->parseIdentity($path, ImportDriver::XLSX);

        self::assertSame(['sku', 'qty'], $result->headers);
        self::assertSame('A-001', $result->rows[0]['sku']);
        self::assertSame(5, $result->rows[1]['qty']);
    }

    private function parseIdentity(string $filePath, ImportDriver $driver, ?ImportOptions $options = null): ImportResult
    {
        $definition = new class ($driver, $options ?? new ImportOptions()) extends AbstractImportDefinition {
            public function __construct(
                private readonly ImportDriver $driverValue,
                private readonly ImportOptions $optionsValue,
            ) {
            }

            public function driver(): ImportDriver
            {
                return $this->driverValue;
            }

            public function options(): ImportOptions
            {
                return $this->optionsValue;
            }

            public function mapRow(array $row, int $lineNumber): RowMapResult
            {
                return RowMapResult::ok($row);
            }
        };

        return new SpreadsheetParser()->parse(ImportSource::fromPath($filePath), $definition);
    }

    private function tempFile(string $suffix, string $content): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'psb-export-test-');
        if (!is_string($tmp)) {
            self::fail('Cannot create temporary path.');
        }

        $path = $tmp . $suffix;
        rename($tmp, $path);
        file_put_contents($path, $content);
        $this->files[] = $path;

        return $path;
    }
}
