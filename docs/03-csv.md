# CSV

`CsvOptions` управляет разбором CSV:

- `delimiters` — список допустимых разделителей (например `[';', ',', "\t"]`);
- `enclosure` — символ обрамления (по умолчанию `"` );
- `escape` — escape-символ (по умолчанию `\`);
- `encoding` — `auto`, `utf-8`, `windows-1251`.

Пример:

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
            csv: new CsvOptions(
                delimiters: [';', ','],
                enclosure: '"',
                escape: '\\',
                encoding: 'auto',
            ),
        );
    }

    public function mapRow(array $row, int $lineNumber): RowMapResult
    {
        return RowMapResult::ok($row);
    }
};

$parser = new SpreadsheetParser();
$result = $parser->parse(ImportSource::fromPath('/path/to/contacts.csv'), $definition);
```

Что поддерживается:
- BOM в начале файла;
- UTF-8/Windows-1251;
- переносы строк внутри ячеек (multiline cell).
