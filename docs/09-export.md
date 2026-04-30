# Export

Для генерации таблиц используйте `SpreadsheetWriter`.

Пример:

```php
use PhpSoftBox\SpreadsheetParser\ImportDriver;
use PhpSoftBox\SpreadsheetParser\SpreadsheetCell;
use PhpSoftBox\SpreadsheetParser\SpreadsheetWriter;

$writer = new SpreadsheetWriter();

$content = $writer->write(
    driver: ImportDriver::XLSX,
    headers: ['id', 'name', 'barcode'],
    rows: [
        ['id' => 1, 'name' => 'Товар 1', 'barcode' => SpreadsheetCell::text('4601234567890')],
        ['id' => 2, 'name' => 'Товар 2', 'barcode' => SpreadsheetCell::text('4601234567891')],
    ],
    sheetName: 'Products',
);
```

Поддерживается:
- `ImportDriver::CSV`
- `ImportDriver::XLSX`

Поведение:
- если `headers` не переданы, они определяются из первой строки;
- в `CSV` добавляется UTF-8 BOM;
- в `CSV` строковые значения пишутся в кавычках, числовые значения — без кавычек;
- для `XLSX` генерируется один лист с именем `sheetName`.

## Явный тип ячейки

По умолчанию writer использует PHP-тип значения:
- `int`/`float` экспортируются как числа;
- `bool` экспортируется как boolean для `XLSX`;
- `string` экспортируется как текст.

Если значение в PHP числовое, но в таблице его нужно сохранить как текст, используйте `SpreadsheetCell::text(...)`.
Это полезно для артикулов, штрихкодов и других идентификаторов с ведущими нулями:

```php
$content = $writer->write(
    driver: ImportDriver::CSV,
    headers: ['sku', 'qty'],
    rows: [
        ['sku' => SpreadsheetCell::text('001234'), 'qty' => SpreadsheetCell::number(10)],
    ],
);
```
