# Definitions

`ImportDefinitionInterface` описывает контракт импорта:

- `driver()` — какой драйвер использовать (`CSV` или `XLSX`);
- `options()` — лимиты/настройки импорта;
- `allowHeaderless()` — разрешить файлы без заголовка;
- `mapRow()` — преобразование строки в целевую структуру или ошибка строки.

Поведение `allowHeaderless()`:
- `false` (по умолчанию): первая непустая строка трактуется как заголовки;
- `true`: первая непустая строка трактуется как данные, заголовки генерируются как `column_1`, `column_2`, ...

Пример:

```php
use PhpSoftBox\SpreadsheetParser\AbstractImportDefinition;
use PhpSoftBox\SpreadsheetParser\ImportDriver;
use PhpSoftBox\SpreadsheetParser\ImportOptions;
use PhpSoftBox\SpreadsheetParser\RowMapResult;

final class UserImportDefinition extends AbstractImportDefinition
{
    public function driver(): ImportDriver
    {
        return ImportDriver::CSV;
    }

    public function options(): ImportOptions
    {
        return new ImportOptions(maxRows: 10000, maxColumns: 20);
    }

    public function mapRow(array $row, int $lineNumber): RowMapResult
    {
        $email = trim((string) ($row['email'] ?? ''));
        if ($email === '') {
            return RowMapResult::error('Email обязателен.', 'email');
        }

        return RowMapResult::ok([
            'email' => $email,
            'name' => (string) ($row['name'] ?? ''),
            'line_number' => $lineNumber,
        ]);
    }
}
```

Запуск:

```php
use PhpSoftBox\SpreadsheetParser\ImportSource;
use PhpSoftBox\SpreadsheetParser\SpreadsheetParser;

$parser = new SpreadsheetParser();
$result = $parser->parse(ImportSource::fromPath('/path/to/users.csv'), new UserImportDefinition());
```
