# PhpSoftBox SpreadsheetParser

`SpreadsheetParser` — компонент импорта табличных файлов `CSV/XLSX` для PhpSoftBox.

## Возможности

- `parse(...)` по definition с явным выбором драйвера (`CSV`/`XLSX`);
- в `parse(...)` тип не определяется автоматически: драйвер задаётся definition;
- источник импорта как путь к файлу или сырой контент;
- чтение CSV/XLSX;
- выбор листа Excel (по умолчанию первый, по индексу, по имени);
- определение заголовков и маппинг строк в ассоциативные массивы;
- пропуск пустых строк;
- проверка обязательных колонок;
- валидация строк через `ApiSchema`/`Validator`;
- ошибки по строкам;
- лимиты на размер файла, число строк и колонок;
- поддержка CSV кодировок `UTF-8`, `Windows-1251`, автоопределение;
- безопасное чтение XLSX без выполнения формул;
- логирование через `Psr\Log\LoggerInterface`.

## Быстрый старт (definition API)

```php
use PhpSoftBox\SpreadsheetParser\AbstractImportDefinition;
use PhpSoftBox\SpreadsheetParser\CsvOptions;
use PhpSoftBox\SpreadsheetParser\ImportDriver;
use PhpSoftBox\SpreadsheetParser\ImportOptions;
use PhpSoftBox\SpreadsheetParser\ImportSource;
use PhpSoftBox\SpreadsheetParser\RowMapResult;
use PhpSoftBox\SpreadsheetParser\SpreadsheetParser;

$definition = new class extends AbstractImportDefinition {
    public function driver(): ImportDriver
    {
        return ImportDriver::CSV;
    }

    public function options(): ImportOptions
    {
        return new ImportOptions(
            csv: new CsvOptions(delimiters: [';', ',']),
            requiredColumns: ['email', 'name'],
        );
    }

    public function mapRow(array $row, int $lineNumber): RowMapResult
    {
        return RowMapResult::ok([
            'email' => (string) ($row['email'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
        ]);
    }
};

$parser = new SpreadsheetParser(logger: $logger);
$result = $parser->parse(ImportSource::fromPath('/path/to/users.csv'), $definition);
```

## Пример: headerless CSV

```php
public function allowHeaderless(): bool
{
    return true;
}

public function mapRow(array $row, int $lineNumber): RowMapResult
{
    $value = trim((string) ($row['column_1'] ?? ''));
    return $value === ''
        ? RowMapResult::error('Строка не содержит значения.', 'raw_value')
        : RowMapResult::ok(['raw_value' => $value, 'line_number' => $lineNumber]);
}
```

Если `allowHeaderless()` возвращает `true`, первая непустая строка считается данными, а заголовки генерируются автоматически (`column_1`, `column_2`, ...).

## Пример: импорт из контента

```php
$content = "email;name\nuser@example.com;Ivan\n";
$result = $parser->parse(ImportSource::fromContent($content, 'users.csv'), $definition);
```

## Оглавление

- [Документация](docs/index.md)
- [Quick Start](docs/02-quick-start.md)
- [Definitions](docs/08-definitions.md)
- [CSV](docs/03-csv.md)
- [XLSX](docs/04-xlsx.md)
- [Validation](docs/05-validation.md)
- [Limits & Errors](docs/06-limits-and-errors.md)
- [Logging](docs/07-logging.md)
