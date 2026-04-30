<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser\Tests;

use PhpSoftBox\SpreadsheetParser\AbstractImportDefinition;
use PhpSoftBox\SpreadsheetParser\ApiSchemaRowValidator;
use PhpSoftBox\SpreadsheetParser\CsvOptions;
use PhpSoftBox\SpreadsheetParser\ImportDriver;
use PhpSoftBox\SpreadsheetParser\ImportOptions;
use PhpSoftBox\SpreadsheetParser\ImportResult;
use PhpSoftBox\SpreadsheetParser\ImportSource;
use PhpSoftBox\SpreadsheetParser\RowMapResult;
use PhpSoftBox\SpreadsheetParser\SheetSelection;
use PhpSoftBox\SpreadsheetParser\SpreadsheetParser;
use PhpSoftBox\SpreadsheetParser\Tests\Fixtures\ContactRowSchema;
use PhpSoftBox\SpreadsheetParser\Tests\Support\InMemoryLogger;
use PhpSoftBox\SpreadsheetParser\Tests\Support\XlsxTestFileFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function file_put_contents;
use function implode;
use function is_string;
use function mb_convert_encoding;
use function rename;
use function str_contains;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use function unlink;

#[CoversClass(SpreadsheetParser::class)]
final class SpreadsheetParserTest extends TestCase
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
     * Проверяет импорт CSV с разделителем запятая.
     */
    public function importsCsvWithCommaDelimiter(): void
    {
        $file = $this->csv("email,name,phone\nuser@example.com,Ivan,+79990000000\n");

        $result = $this->parseWithIdentityDefinition($file, ImportDriver::CSV);

        self::assertSame(['email', 'name', 'phone'], $result->headers);
        self::assertCount(1, $result->rows);
        self::assertSame('user@example.com', $result->rows[0]['email']);
        self::assertFalse($result->hasErrors());
    }

    #[Test]
    /**
     * Проверяет импорт CSV с разделителем точка с запятой.
     */
    public function importsCsvWithSemicolonDelimiter(): void
    {
        $file = $this->csv("email;name;phone\nuser@example.com;Ivan;+79990000000\n");

        $result = $this->parseWithIdentityDefinition(
            $file,
            ImportDriver::CSV,
            new ImportOptions(csv: new CsvOptions(delimiters: [';'])),
        );

        self::assertSame(['email', 'name', 'phone'], $result->headers);
        self::assertCount(1, $result->rows);
        self::assertSame('Ivan', $result->rows[0]['name']);
    }

    #[Test]
    /**
     * Проверяет автоопределение кодировки Windows-1251 для CSV.
     */
    public function importsWindows1251Csv(): void
    {
        $content = mb_convert_encoding("email;name\nuser@example.com;Иван\n", 'Windows-1251', 'UTF-8');
        $file    = $this->csv($content);

        $result = $this->parseWithIdentityDefinition(
            $file,
            ImportDriver::CSV,
            new ImportOptions(csv: new CsvOptions(delimiters: [';'], encoding: 'auto')),
        );

        self::assertSame('Иван', $result->rows[0]['name']);
    }

    #[Test]
    /**
     * Проверяет импорт XLSX с одним листом.
     */
    public function importsXlsxWithSingleSheet(): void
    {
        $file = $this->xlsx([
            [
                'name' => 'Sheet1',
                'rows' => [
                    ['email', 'name', 'phone'],
                    ['user@example.com', 'Ivan', '+79990000000'],
                ],
            ],
        ]);

        $result = $this->parseWithIdentityDefinition($file, ImportDriver::XLSX);

        self::assertSame(['email', 'name', 'phone'], $result->headers);
        self::assertSame('Ivan', $result->rows[0]['name']);
        self::assertFalse($result->hasErrors());
    }

    #[Test]
    /**
     * Проверяет выбор листа Excel по индексу.
     */
    public function importsSelectedSheetByIndex(): void
    {
        $file = $this->xlsx([
            [
                'name' => 'First',
                'rows' => [
                    ['email', 'name'],
                    ['first@example.com', 'First'],
                ],
            ],
            [
                'name' => 'Second',
                'rows' => [
                    ['email', 'name'],
                    ['second@example.com', 'Second'],
                ],
            ],
        ]);

        $result = $this->parseWithIdentityDefinition(
            $file,
            ImportDriver::XLSX,
            new ImportOptions(sheet: SheetSelection::byIndex(1)),
        );

        self::assertSame('second@example.com', $result->rows[0]['email']);
    }

    #[Test]
    /**
     * Проверяет выбор листа Excel по имени.
     */
    public function importsSelectedSheetByName(): void
    {
        $file = $this->xlsx([
            [
                'name' => 'Users',
                'rows' => [
                    ['email', 'name'],
                    ['users@example.com', 'Users'],
                ],
            ],
            [
                'name' => 'Orders',
                'rows' => [
                    ['email', 'name'],
                    ['orders@example.com', 'Orders'],
                ],
            ],
        ]);

        $result = $this->parseWithIdentityDefinition(
            $file,
            ImportDriver::XLSX,
            new ImportOptions(sheet: SheetSelection::byName('Orders')),
        );

        self::assertSame('orders@example.com', $result->rows[0]['email']);
    }

    #[Test]
    /**
     * Проверяет ошибку, если заголовки колонок не определяются.
     */
    public function returnsErrorForFileWithoutHeaders(): void
    {
        $file = $this->csv("1,2,3\nuser@example.com,Ivan,+79990000000\n");

        $result = $this->parseWithIdentityDefinition($file, ImportDriver::CSV);

        self::assertSame([], $result->rows);
        self::assertNotEmpty($result->globalErrors);
        self::assertTrue(str_contains(implode(' ', $result->globalErrors), 'заголов'));
    }

    #[Test]
    /**
     * Проверяет, что пустые строки пропускаются.
     */
    public function skipsEmptyRows(): void
    {
        $file = $this->csv("email,name\n\nuser1@example.com,Ivan\n,\nuser2@example.com,Petr\n");

        $result = $this->parseWithIdentityDefinition($file, ImportDriver::CSV);

        self::assertCount(2, $result->rows);
        self::assertSame('user1@example.com', $result->rows[0]['email']);
        self::assertSame('user2@example.com', $result->rows[1]['email']);
    }

    #[Test]
    /**
     * Проверяет, что CSV с переносом строки внутри ячейки читается корректно.
     */
    public function importsCsvWithMultilineCell(): void
    {
        $file = $this->csv("email,name,comment\nuser@example.com,Ivan,\"line1\nline2\"\n");

        $result = $this->parseWithIdentityDefinition($file, ImportDriver::CSV);

        self::assertCount(1, $result->rows);
        self::assertSame("line1\nline2", $result->rows[0]['comment']);
    }

    #[Test]
    /**
     * Проверяет ошибку при отсутствии обязательных колонок.
     */
    public function returnsErrorForMissingRequiredColumns(): void
    {
        $file = $this->csv("email,name\nuser@example.com,Ivan\n");

        $result = $this->parseWithIdentityDefinition(
            $file,
            ImportDriver::CSV,
            new ImportOptions(requiredColumns: ['email', 'name', 'phone']),
        );

        self::assertSame([], $result->rows);
        self::assertNotEmpty($result->globalErrors);
        self::assertTrue(str_contains(implode(' ', $result->globalErrors), 'phone'));
    }

    #[Test]
    /**
     * Проверяет обработку повреждённого XLSX без fatal error.
     */
    public function returnsParsingErrorForCorruptedFile(): void
    {
        $file = $this->tempFile('.xlsx', 'not-a-valid-xlsx-content');

        $result = $this->parseWithIdentityDefinition($file, ImportDriver::XLSX);

        self::assertTrue($result->hasErrors());
        self::assertNotEmpty($result->globalErrors);
    }

    #[Test]
    /**
     * Проверяет ограничение максимального размера файла.
     */
    public function returnsErrorWhenFileSizeLimitExceeded(): void
    {
        $file = $this->csv("email,name\n" . str_repeat('a', 256) . ",Ivan\n");

        $result = $this->parseWithIdentityDefinition(
            $file,
            ImportDriver::CSV,
            new ImportOptions(maxFileSizeBytes: 16),
        );

        self::assertTrue($result->hasErrors());
        self::assertTrue(str_contains(implode(' ', $result->globalErrors), 'лимит'));
    }

    #[Test]
    /**
     * Проверяет конвертацию Excel serial date в строковое значение даты.
     */
    public function convertsExcelDateCells(): void
    {
        $stylesXml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="0"/>'
            . '<fonts count="1"><font/></fonts>'
            . '<fills count="1"><fill/></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0"/><xf numFmtId="14" applyNumberFormat="1"/></cellXfs>'
            . '</styleSheet>';

        $file = $this->xlsx(
            [
                [
                    'name' => 'Sheet1',
                    'rows' => [
                        ['email', 'date'],
                        ['user@example.com', ['value' => 45292, 'style' => 1]],
                    ],
                ],
            ],
            $stylesXml,
        );

        $result = $this->parseWithIdentityDefinition($file, ImportDriver::XLSX);

        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $result->rows[0]['date']);
    }

    #[Test]
    /**
     * Проверяет, что формулы Excel не исполняются, а берётся cached value.
     */
    public function doesNotExecuteExcelFormulasAndUsesCachedValue(): void
    {
        $file = $this->xlsx([
            [
                'name' => 'Sheet1',
                'rows' => [
                    ['a', 'b', 'sum'],
                    [2, 2, ['formula' => 'A2+B2', 'value' => 4]],
                ],
            ],
        ]);

        $result = $this->parseWithIdentityDefinition($file, ImportDriver::XLSX);

        self::assertSame(4, $result->rows[0]['sum']);
    }

    #[Test]
    /**
     * Проверяет корректную обработку UTF-8 BOM в начале CSV.
     */
    public function importsCsvWithBom(): void
    {
        $file = $this->csv("\xEF\xBB\xBFemail,name\nuser@example.com,Ivan\n");

        $result = $this->parseWithIdentityDefinition($file, ImportDriver::CSV);

        self::assertSame('email', $result->headers[0]);
        self::assertSame('user@example.com', $result->rows[0]['email']);
    }

    #[Test]
    /**
     * Проверяет ошибки по строкам через валидацию ApiSchema.
     */
    public function returnsRowErrorsViaApiSchemaValidator(): void
    {
        $file = $this->csv("email,name,phone\ninvalid-email,Ivan,+79990000000\n");

        $result = $this->parseWithIdentityDefinition(
            $file,
            ImportDriver::CSV,
            new ImportOptions(rowValidator: new ApiSchemaRowValidator(ContactRowSchema::class)),
        );

        self::assertCount(0, $result->rows);
        self::assertCount(1, $result->rowErrors);
        self::assertTrue(str_contains(implode(' ', array_map(
            static fn (array $list): string => implode(' ', $list),
            $result->rowErrors[0]->errors,
        )), 'email'));
    }

    #[Test]
    /**
     * Проверяет логирование старта/завершения импорта без персональных данных.
     */
    public function writesImportLogsWithoutSensitiveData(): void
    {
        $logger = new InMemoryLogger();
        $file   = $this->csv("email,name\nuser@example.com,Ivan\n");

        $this->parseWithIdentityDefinition($file, ImportDriver::CSV, logger: $logger);

        self::assertNotEmpty($logger->records);
        self::assertTrue($this->hasLogMessage($logger, 'Import started'));
        self::assertTrue($this->hasLogMessage($logger, 'Import completed'));
        self::assertFalse($this->contextContainsUserEmail($logger, 'user@example.com'));
    }

    #[Test]
    /**
     * Проверяет логирование превышения лимита строк в definition API.
     */
    public function logsRowsLimitExceededForDefinitionImport(): void
    {
        $logger = new InMemoryLogger();
        $file   = $this->csv("email,name\nuser1@example.com,Ivan\nuser2@example.com,Petr\n");

        $definition = new class () extends AbstractImportDefinition {
            public function driver(): ImportDriver
            {
                return ImportDriver::CSV;
            }

            public function options(): ImportOptions
            {
                return new ImportOptions(maxRows: 1);
            }

            public function mapRow(array $row, int $lineNumber): RowMapResult
            {
                return RowMapResult::ok($row);
            }
        };

        $result = new SpreadsheetParser(logger: $logger)->parse(ImportSource::fromPath($file), $definition);

        self::assertTrue($result->hasErrors());
        self::assertCount(1, $result->rowErrors);
        self::assertTrue($this->hasLogMessage($logger, 'Import limit exceeded: max rows'));
    }

    #[Test]
    /**
     * Проверяет логирование превышения лимита размера файла для content source.
     */
    public function logsFileSizeLimitExceededForDefinitionImportFromContent(): void
    {
        $logger     = new InMemoryLogger();
        $bigContent = "email,name\n" . str_repeat('a', 256) . ",Ivan\n";

        $definition = new class () extends AbstractImportDefinition {
            public function driver(): ImportDriver
            {
                return ImportDriver::CSV;
            }

            public function options(): ImportOptions
            {
                return new ImportOptions(maxFileSizeBytes: 16);
            }

            public function mapRow(array $row, int $lineNumber): RowMapResult
            {
                return RowMapResult::ok($row);
            }
        };

        $result = new SpreadsheetParser(logger: $logger)->parse(
            ImportSource::fromContent($bigContent, 'users.csv'),
            $definition,
        );

        self::assertTrue($result->hasErrors());
        self::assertNotEmpty($result->globalErrors);
        self::assertTrue($this->hasLogMessage($logger, 'Import limit exceeded: max file size'));
    }

    #[Test]
    /**
     * Проверяет импорт по definition с явным CSV драйвером без автоопределения по расширению.
     */
    public function parsesCsvByDefinitionWithoutFileTypeDetection(): void
    {
        $file = $this->tempFile('.bin', "email,name\nuser@example.com,Ivan\n");

        $definition = new class () extends AbstractImportDefinition {
            public function driver(): ImportDriver
            {
                return ImportDriver::CSV;
            }

            public function options(): ImportOptions
            {
                return new ImportOptions(csv: new CsvOptions(delimiters: [',']));
            }

            public function mapRow(array $row, int $lineNumber): RowMapResult
            {
                return RowMapResult::ok([
                    'email' => (string) ($row['email'] ?? ''),
                    'name'  => (string) ($row['name'] ?? ''),
                ]);
            }
        };

        $result = new SpreadsheetParser()->parse(ImportSource::fromPath($file), $definition);

        self::assertFalse($result->hasErrors());
        self::assertCount(1, $result->rows);
        self::assertSame('Ivan', $result->rows[0]['name']);
    }

    #[Test]
    /**
     * Проверяет режим allowHeaderless в definition для CSV без заголовка.
     */
    public function parsesHeaderlessCsvByDefinition(): void
    {
        $file = $this->csv("010460123456789021ABC\n010460123456789021DEF\n");

        $definition = new class () extends AbstractImportDefinition {
            public function driver(): ImportDriver
            {
                return ImportDriver::CSV;
            }

            public function allowHeaderless(): bool
            {
                return true;
            }

            public function mapRow(array $row, int $lineNumber): RowMapResult
            {
                $raw = trim((string) ($row['column_1'] ?? ''));
                if ($raw === '') {
                    return RowMapResult::error('Строка не содержит значения.', 'raw_value');
                }

                return RowMapResult::ok(['raw_value' => $raw, 'line_number' => $lineNumber]);
            }
        };

        $result = new SpreadsheetParser()->parse(ImportSource::fromPath($file), $definition);

        self::assertFalse($result->hasErrors());
        self::assertCount(2, $result->rows);
        self::assertSame('010460123456789021ABC', $result->rows[0]['raw_value']);
    }

    #[Test]
    /**
     * Проверяет импорт из content источника через definition.
     */
    public function parsesContentSourceByDefinition(): void
    {
        $content    = "email;name\nuser@example.com;Ivan\n";
        $definition = new class () extends AbstractImportDefinition {
            public function driver(): ImportDriver
            {
                return ImportDriver::CSV;
            }

            public function options(): ImportOptions
            {
                return new ImportOptions(csv: new CsvOptions(delimiters: [';']));
            }

            public function mapRow(array $row, int $lineNumber): RowMapResult
            {
                return RowMapResult::ok([
                    'line'  => $lineNumber,
                    'email' => (string) ($row['email'] ?? ''),
                ]);
            }
        };

        $result = new SpreadsheetParser()->parse(
            ImportSource::fromContent($content, 'contacts.csv'),
            $definition,
        );

        self::assertFalse($result->hasErrors());
        self::assertCount(1, $result->rows);
        self::assertSame(2, $result->rows[0]['line']);
    }

    #[Test]
    /**
     * Проверяет импорт XLSX по definition с явным драйвером.
     */
    public function parsesXlsxByDefinitionDriver(): void
    {
        $file = $this->xlsx([
            [
                'name' => 'Sheet1',
                'rows' => [
                    ['email', 'name'],
                    ['user@example.com', 'Ivan'],
                ],
            ],
        ]);

        $definition = new class () extends AbstractImportDefinition {
            public function driver(): ImportDriver
            {
                return ImportDriver::XLSX;
            }

            public function mapRow(array $row, int $lineNumber): RowMapResult
            {
                return RowMapResult::ok([
                    'name' => (string) ($row['name'] ?? ''),
                    'line' => $lineNumber,
                ]);
            }
        };

        $result = new SpreadsheetParser()->parse(ImportSource::fromPath($file), $definition);

        self::assertFalse($result->hasErrors());
        self::assertCount(1, $result->rows);
        self::assertSame('Ivan', $result->rows[0]['name']);
        self::assertSame(2, $result->rows[0]['line']);
    }

    private function csv(string $content): string
    {
        return $this->tempFile('.csv', $content);
    }

    private function parseWithIdentityDefinition(
        string $filePath,
        ImportDriver $driver,
        ?ImportOptions $options = null,
        ?InMemoryLogger $logger = null,
    ): ImportResult {
        $options ??= new ImportOptions();

        $definition = new class ($driver, $options) extends AbstractImportDefinition {
            public function __construct(
                private readonly ImportDriver $driver,
                private readonly ImportOptions $options,
            ) {
            }

            public function driver(): ImportDriver
            {
                return $this->driver;
            }

            public function options(): ImportOptions
            {
                return $this->options;
            }

            public function mapRow(array $row, int $lineNumber): RowMapResult
            {
                return RowMapResult::ok($row);
            }
        };

        $parser = $logger === null
            ? new SpreadsheetParser()
            : new SpreadsheetParser(logger: $logger);

        return $parser->parse(ImportSource::fromPath($filePath), $definition);
    }

    /**
     * @param list<array{name: string, rows: list<list<mixed>>}> $sheets
     */
    private function xlsx(array $sheets, ?string $stylesXml = null): string
    {
        $file          = XlsxTestFileFactory::create($sheets, $stylesXml);
        $this->files[] = $file;

        return $file;
    }

    private function tempFile(string $extension, string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'psb-import-');
        if (!is_string($path)) {
            self::fail('Cannot create temporary file.');
        }

        $target = $path . $extension;
        rename($path, $target);
        file_put_contents($target, $content);
        $this->files[] = $target;

        return $target;
    }

    private function hasLogMessage(InMemoryLogger $logger, string $message): bool
    {
        foreach ($logger->records as $record) {
            if ($record['message'] === $message) {
                return true;
            }
        }

        return false;
    }

    private function contextContainsUserEmail(InMemoryLogger $logger, string $email): bool
    {
        foreach ($logger->records as $record) {
            foreach ($record['context'] as $value) {
                if (is_string($value) && str_contains($value, $email)) {
                    return true;
                }
            }
        }

        return false;
    }
}
