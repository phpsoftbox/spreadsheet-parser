# Limits & Errors

Лимиты настраиваются через `ImportOptions`:

- `maxFileSizeBytes`;
- `maxRows`;
- `maxColumns`.

Пример:

```php
use PhpSoftBox\SpreadsheetParser\AbstractImportDefinition;
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
            maxFileSizeBytes: 5_000_000,
            maxRows: 20_000,
            maxColumns: 100,
        );
    }

    public function mapRow(array $row, int $lineNumber): RowMapResult
    {
        return RowMapResult::ok($row);
    }
};

$parser = new SpreadsheetParser();
$result = $parser->parse(ImportSource::fromPath('/path/to/users.csv'), $definition);
```

Типы ошибок:

- `globalErrors`:
  - повреждённый файл;
  - недопустимый формат;
  - превышение лимита размера;
  - отсутствие обязательных колонок;
  - ошибка парсинга уровня файла.
- `rowErrors`:
  - ошибки валидации конкретной строки;
  - превышение лимитов по строке.
