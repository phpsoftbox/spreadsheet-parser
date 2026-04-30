# XLSX

Выбор листа:

- первый лист по умолчанию;
- `SheetSelection::byIndex(int $index)`;
- `SheetSelection::byName(string $name)`.

Пример:

```php
use PhpSoftBox\SpreadsheetParser\AbstractImportDefinition;
use PhpSoftBox\SpreadsheetParser\ImportDriver;
use PhpSoftBox\SpreadsheetParser\ImportOptions;
use PhpSoftBox\SpreadsheetParser\ImportSource;
use PhpSoftBox\SpreadsheetParser\RowMapResult;
use PhpSoftBox\SpreadsheetParser\SheetSelection;
use PhpSoftBox\SpreadsheetParser\SpreadsheetParser;

$definition = new class extends AbstractImportDefinition {
    public function driver(): ImportDriver
    {
        return ImportDriver::XLSX;
    }

    public function options(): ImportOptions
    {
        return new ImportOptions(sheet: SheetSelection::byIndex(1));
    }

    public function mapRow(array $row, int $lineNumber): RowMapResult
    {
        return RowMapResult::ok($row);
    }
};

$parser = new SpreadsheetParser();
$result = $parser->parse(ImportSource::fromPath('/path/to/report.xlsx'), $definition);
```

Поведение:
- читаются только значения ячеек;
- формулы не исполняются;
- стили и изображения не импортируются как данные;
- поддерживаются числовые, строковые, булевы, пустые значения;
- Excel serial date конвертируется в строковый формат даты.
