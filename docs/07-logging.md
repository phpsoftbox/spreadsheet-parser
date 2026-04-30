# Logging

`SpreadsheetParser` принимает `Psr\Log\LoggerInterface` через конструктор.

Логируются события:
- начало импорта;
- тип файла;
- завершение импорта (количество строк и ошибок);
- исключения парсинга;
- превышение лимитов.

Пример:

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
        return RowMapResult::ok($row);
    }
};

$parser = new SpreadsheetParser(logger: $logger);
$result = $parser->parse(ImportSource::fromPath('/path/to/users.csv'), $definition);
```

Рекомендация:
- не логировать персональные данные из строк импорта без явной бизнес-необходимости.
