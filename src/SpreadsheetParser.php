<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

use PhpSoftBox\SpreadsheetParser\Csv\CsvReader;
use PhpSoftBox\SpreadsheetParser\Excel\XlsxReader;
use PhpSoftBox\SpreadsheetParser\Exception\ImportException;
use PhpSoftBox\SpreadsheetParser\Exception\ParsingException;
use PhpSoftBox\SpreadsheetParser\Internal\RawTable;
use PhpSoftBox\Validator\Rule\PresentValidation;
use PhpSoftBox\Validator\Validator;
use PhpSoftBox\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

use function array_fill_keys;
use function array_key_first;
use function count;
use function file_put_contents;
use function filesize;
use function is_file;
use function is_int;
use function is_readable;
use function is_string;
use function max;
use function min;
use function pathinfo;
use function preg_match;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use function unlink;

use const PATHINFO_BASENAME;
use const PHP_INT_MAX;

final class SpreadsheetParser
{
    public function __construct(
        private readonly CsvReader $csvReader = new CsvReader(),
        private readonly XlsxReader $xlsxReader = new XlsxReader(),
        private readonly ValidatorInterface $validator = new Validator(),
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function parse(ImportSource $source, ImportDefinitionInterface $definition): ImportResult
    {
        $options = $definition->options();
        $type    = $this->importTypeFromDriver($definition->driver());

        $filePath = '';
        $fileName = $source->fileName ?? 'import-source';
        $cleanup  = false;

        try {
            $prepared = $this->prepareSource($source, $options);
            $filePath = $prepared['path'];
            $fileName = $prepared['file_name'];
            $cleanup  = $prepared['cleanup'];

            $this->logger->info('Import started', [
                'file_name' => $fileName,
                'file_type' => $type->value,
            ]);

            $table  = $this->readTableByDriver($definition->driver(), $filePath, $options);
            $result = $this->mapTableToResultByDefinition(
                type: $type,
                table: $table,
                options: $options,
                definition: $definition,
            );

            $this->logger->info('Import completed', [
                'file_name' => $fileName,
                'file_type' => $type->value,
                'rows'      => $result->totalRows,
                'errors'    => count($result->rowErrors) + count($result->globalErrors),
            ]);

            return $result;
        } catch (Throwable $exception) {
            $this->logger->error('Import failed', [
                'file_name'         => $fileName,
                'exception_class'   => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return new ImportResult(
                fileType: $type,
                rows: [],
                headers: [],
                rowErrors: [],
                globalErrors: ['Ошибка парсинга файла: ' . $exception->getMessage()],
                totalRows: 0,
            );
        } finally {
            if ($cleanup && $filePath !== '') {
                @unlink($filePath);
            }
        }
    }

    private function importTypeFromDriver(ImportDriver $driver): ImportType
    {
        return match ($driver) {
            ImportDriver::CSV  => ImportType::CSV,
            ImportDriver::XLSX => ImportType::XLSX,
        };
    }

    /**
     * @return array{path: string, file_name: string, cleanup: bool}
     */
    private function prepareSource(ImportSource $source, ImportOptions $options): array
    {
        if ($source->path !== null) {
            $this->assertFile($source->path, $options);

            return [
                'path'      => $source->path,
                'file_name' => $source->fileName ?? pathinfo($source->path, PATHINFO_BASENAME),
                'cleanup'   => false,
            ];
        }

        $content = $source->content;
        if ($content === null || $content === '') {
            throw new ParsingException('Import source content is empty.');
        }

        if (strlen($content) > $options->maxFileSizeBytes) {
            $this->logger->warning('Import limit exceeded: max file size', [
                'actual' => strlen($content),
                'max'    => $options->maxFileSizeBytes,
            ]);

            throw new ImportException('Превышен лимит размера файла.');
        }

        $tempPath = $this->createTempSourcePath($content);
        $this->assertFile($tempPath, $options);

        return [
            'path'      => $tempPath,
            'file_name' => $source->fileName ?? pathinfo($tempPath, PATHINFO_BASENAME),
            'cleanup'   => true,
        ];
    }

    private function createTempSourcePath(string $content): string
    {
        // tempnam() атомарно создаёт уникальный файл с правами 0600: предсказуемое имя в общем /tmp позволяло
        // подменить его симлинком или прочитать чужой импорт.
        $tempPath = tempnam(sys_get_temp_dir(), 'spreadsheet-parser-');
        if (!is_string($tempPath)) {
            throw new ParsingException('Cannot create temporary import file.');
        }

        $written = file_put_contents($tempPath, $content);
        if (!is_int($written) || $written <= 0) {
            @unlink($tempPath);

            throw new ParsingException('Cannot create temporary import file.');
        }

        return $tempPath;
    }

    private function readTableByDriver(ImportDriver $driver, string $filePath, ImportOptions $options): RawTable
    {
        // Заголовок, maxRows строк данных и ещё одна, чтобы сработал лимит строк: остальное не читается в память.
        $rowsToRead = min($options->maxRows, PHP_INT_MAX - 2) + 2;

        return match ($driver) {
            ImportDriver::CSV  => $this->csvReader->read($filePath, $options->csv, $rowsToRead),
            ImportDriver::XLSX => $this->xlsxReader->read(
                $filePath,
                $options->sheet,
                $rowsToRead,
                $options->maxUncompressedBytes,
            ),
        };
    }

    private function assertFile(string $filePath, ImportOptions $options): void
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new ParsingException('File does not exist or is not readable.');
        }

        $size = filesize($filePath);
        if (!is_int($size) || $size < 0) {
            throw new ParsingException('Cannot detect file size.');
        }

        if ($size > $options->maxFileSizeBytes) {
            $this->logger->warning('Import limit exceeded: max file size', [
                'actual' => $size,
                'max'    => $options->maxFileSizeBytes,
            ]);

            throw new ImportException('Превышен лимит размера файла.');
        }
    }

    private function mapTableToResultByDefinition(
        ImportType $type,
        RawTable $table,
        ImportOptions $options,
        ImportDefinitionInterface $definition,
    ): ImportResult {
        $firstRowIndex = array_key_first($table->rows);
        if ($firstRowIndex === null) {
            return new ImportResult($type, [], [], [], ['Файл пустой или не содержит данных.'], 0);
        }

        // Ширина проверяется до построения заголовков: ячейка в дальней колонке не должна раздувать строку.
        $firstRow = $table->rows[$firstRowIndex];
        if ($this->rowWidth($firstRow) > $options->maxColumns) {
            $this->logger->warning('Import limit exceeded: max columns', [
                'actual' => $this->rowWidth($firstRow),
                'max'    => $options->maxColumns,
            ]);

            return new ImportResult($type, [], [], [], ['Превышен лимит количества колонок.'], 0);
        }

        $headers = $definition->allowHeaderless()
            ? $this->buildGeneratedHeaders($firstRow)
            : $this->normalizeHeaders($firstRow);
        if (!$definition->allowHeaderless() && !$this->looksLikeHeaderRow($headers)) {
            return new ImportResult($type, [], $headers, [], ['Не удалось определить строку заголовков.'], 0);
        }

        $requiredColumnsErrors = $this->validateRequiredColumns($headers, $options->requiredColumns);
        if ($requiredColumnsErrors !== []) {
            return new ImportResult($type, [], $headers, [], $requiredColumnsErrors, 0);
        }

        $rows      = [];
        $rowErrors = [];
        $totalRows = 0;

        $headerCount = count($headers);
        foreach ($table->rows as $i => $sourceRow) {
            if ($i === $firstRowIndex && !$definition->allowHeaderless()) {
                continue;
            }

            $lineNumber = $i + 1;
            $totalRows++;

            if ($totalRows > $options->maxRows) {
                $this->logger->warning('Import limit exceeded: max rows', [
                    'actual' => $totalRows,
                    'max'    => $options->maxRows,
                ]);

                $rowErrors[] = new RowError($lineNumber, ['row' => ['Превышен лимит количества строк.']]);
                break;
            }

            $rowWidth = $this->rowWidth($sourceRow);
            if ($rowWidth > $options->maxColumns) {
                $this->logger->warning('Import limit exceeded: row max columns', [
                    'row_number' => $lineNumber,
                    'actual'     => $rowWidth,
                    'max'        => $options->maxColumns,
                ]);

                $rowErrors[] = new RowError($lineNumber, ['row' => ['Превышен лимит количества колонок в строке.']]);
                continue;
            }

            $row = [];
            for ($colIndex = 0; $colIndex < $headerCount; $colIndex++) {
                $header       = $headers[$colIndex];
                $row[$header] = $sourceRow[$colIndex] ?? null;
            }

            if ($options->rowValidator !== null) {
                $validation = $options->rowValidator->validate($row);
                if ($validation->hasErrors()) {
                    $rowErrors[] = new RowError($lineNumber, $validation->errors());
                    continue;
                }
                $row = $validation->filteredData();
            }

            $mapped = $definition->mapRow($row, $lineNumber);
            if (!$mapped->isValid) {
                $errors      = $mapped->errors !== [] ? $mapped->errors : ['row' => ['Ошибка импорта строки.']];
                $rowErrors[] = new RowError($lineNumber, $errors);
                continue;
            }

            $rows[] = $mapped->data;
        }

        return new ImportResult(
            fileType: $type,
            rows: $rows,
            headers: $headers,
            rowErrors: $rowErrors,
            globalErrors: [],
            totalRows: $totalRows,
        );
    }

    /**
     * @param array<int, mixed> $row
     * @return list<string>
     */
    private function buildGeneratedHeaders(array $row): array
    {
        $trimmed = $this->trimTrailingEmptyCells($row);
        if ($trimmed === []) {
            return [];
        }

        $headers = [];
        for ($i = 0; $i < count($trimmed); $i++) {
            $headers[] = 'column_' . ($i + 1);
        }

        return $headers;
    }

    /**
     * @param list<string> $headers
     * @param list<string> $requiredColumns
     * @return list<string>
     */
    private function validateRequiredColumns(array $headers, array $requiredColumns): array
    {
        if ($requiredColumns === []) {
            return [];
        }

        $data  = array_fill_keys($headers, true);
        $rules = [];
        foreach ($requiredColumns as $column) {
            $rules[$column] = [new PresentValidation()];
        }

        $result = $this->validator->validate($data, $rules);
        if (!$result->hasErrors()) {
            return [];
        }

        $errors = [];
        foreach ($result->errors() as $messages) {
            foreach ($messages as $message) {
                $errors[] = $message;
            }
        }

        return $errors;
    }

    /**
     * @param array<int, mixed> $row
     * @return list<string>
     */
    private function normalizeHeaders(array $row): array
    {
        $trimmed = $this->trimTrailingEmptyCells($row);
        if ($trimmed === []) {
            return [];
        }

        $headers = [];
        foreach ($trimmed as $index => $value) {
            $header = trim((string) $value);
            if ($header === '') {
                $header = 'column_' . ($index + 1);
            }

            $headers[] = $header;
        }

        return $headers;
    }

    /**
     * @param list<string> $headers
     */
    private function looksLikeHeaderRow(array $headers): bool
    {
        if ($headers === []) {
            return false;
        }

        $allNumeric = true;
        foreach ($headers as $header) {
            if (preg_match('/^-?\d+(?:\.\d+)?$/', $header) !== 1) {
                $allNumeric = false;
                break;
            }
        }

        return !$allNumeric;
    }

    /**
     * Ширина строки — индекс последней непустой ячейки плюс один; строка может быть разреженной.
     *
     * @param array<int, mixed> $row
     */
    private function rowWidth(array $row): int
    {
        $width = 0;
        foreach ($row as $index => $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }

            $width = max($width, $index + 1);
        }

        return $width;
    }

    /**
     * @param array<int, mixed> $row
     * @return list<mixed>
     */
    private function trimTrailingEmptyCells(array $row): array
    {
        $lastNonEmpty = -1;
        foreach ($row as $index => $value) {
            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            $lastNonEmpty = $index;
        }

        if ($lastNonEmpty < 0) {
            return [];
        }

        $result = [];
        for ($i = 0; $i <= $lastNonEmpty; $i++) {
            $result[] = $row[$i] ?? null;
        }

        return $result;
    }
}
