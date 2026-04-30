# Export

Для генерации таблиц используйте `SpreadsheetWriter`.

Пример:

```php
use PhpSoftBox\SpreadsheetParser\ImportDriver;
use PhpSoftBox\SpreadsheetParser\SpreadsheetWriter;

$writer = new SpreadsheetWriter();

$content = $writer->write(
    driver: ImportDriver::XLSX,
    headers: ['id', 'name', 'barcode'],
    rows: [
        ['id' => 1, 'name' => 'Товар 1', 'barcode' => '4601234567890'],
        ['id' => 2, 'name' => 'Товар 2', 'barcode' => '4601234567891'],
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
- для `XLSX` генерируется один лист с именем `sheetName`.

