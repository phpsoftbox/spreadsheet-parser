# Quick Start

```php
use PhpSoftBox\SpreadsheetParser\AbstractImportDefinition;
use PhpSoftBox\SpreadsheetParser\ImportDriver;
use PhpSoftBox\SpreadsheetParser\ImportSource;
use PhpSoftBox\SpreadsheetParser\RowMapResult;
use PhpSoftBox\SpreadsheetParser\SpreadsheetParser;

$definition = new class extends AbstractImportDefinition {
    public function driver(): ImportDriver
    {
        return ImportDriver::CSV;
    }

    public function mapRow(array $row, int $lineNumber): RowMapResult
    {
        return RowMapResult::ok([
            'email' => (string) ($row['email'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
        ]);
    }
};

$parser = new SpreadsheetParser();
$result = $parser->parse(ImportSource::fromPath('/path/to/users.csv'), $definition);
```

Проверка результата:

```php
if ($result->hasErrors()) {
    // $result->globalErrors
    // $result->rowErrors
}

foreach ($result->rows as $row) {
    // ['email' => '...', 'name' => '...']
}
```
