# Limits & Errors

Лимиты настраиваются через `ImportOptions`:

- `maxFileSizeBytes` — размер файла (для XLSX — сжатого архива), по умолчанию 10 МБ;
- `maxRows` — число непустых строк данных, по умолчанию 10 000;
- `maxColumns` — ширина строки до последней непустой ячейки, по умолчанию 200;
- `maxUncompressedBytes` — распакованный размер каждой XML-части XLSX (лист, `sharedStrings.xml`, `styles.xml`),
  по умолчанию 64 МБ. Защищает от zip-бомбы: небольшой архив не распакуется в гигабайты.

Лимиты применяются при чтении, а не после него:

- читатели не хранят пустые строки и прекращают разбор после `maxRows + 2` непустых строк (заголовок, строки
  данных и одна лишняя, на которой срабатывает ошибка лимита строк);
- ширина строки считается по индексу последней непустой ячейки без заполнения промежутка: ячейка в колонке `XFD`
  не раздувает строку до 16 384 значений; ссылка дальше `XFD` — ошибка парсинга файла;
- при превышении `maxColumns` заголовком возвращается глобальная ошибка без заголовков, строкой данных — ошибка
  этой строки.

Номер строки (`lineNumber`, `RowError::$rowNumber`) — номер строки в файле: для CSV — номер записи с учётом
пустых строк, для XLSX — атрибут `r` элемента `row` (Excel не записывает пустые строки).

Импорт из `ImportSource::fromContent()` записывает содержимое во временный файл через `tempnam()` (уникальное имя,
права `0600`) и удаляет его после разбора.

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
