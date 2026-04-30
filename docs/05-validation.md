# Validation

## Обязательные колонки

`requiredColumns` задаются в `ImportOptions`, которые возвращает definition.

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
        return new ImportOptions(requiredColumns: ['email', 'name', 'phone']);
    }

    public function mapRow(array $row, int $lineNumber): RowMapResult
    {
        return RowMapResult::ok($row);
    }
};

$parser = new SpreadsheetParser();
$result = $parser->parse(ImportSource::fromPath('/path/to/users.csv'), $definition);
```

Если обязательных колонок нет, они попадут в `globalErrors`.

## Валидация строк через ApiSchema

```php
use PhpSoftBox\SpreadsheetParser\ApiSchemaRowValidator;
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
            rowValidator: new ApiSchemaRowValidator(UserImportRowSchema::class),
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

Ошибки отдельных строк попадают в `rowErrors` (`RowError`).
