# Overview

Главные классы компонента:
- `PhpSoftBox\SpreadsheetParser\SpreadsheetParser` — импорт/парсинг;
- `PhpSoftBox\SpreadsheetParser\SpreadsheetWriter` — экспорт/генерация таблиц.

Вход:
- `ImportSource` (путь к файлу или content);
- `ImportDefinitionInterface` (драйвер, опции, маппинг строк).

Важно:
- `parse(...)` не выполняет автоопределение типа файла; драйвер выбирается только через `ImportDefinitionInterface::driver()`.

Выход:
- `ImportResult` с:
  - `headers` — заголовки колонок;
  - `rows` — валидные строки в виде ассоциативных массивов;
  - `rowErrors` — ошибки валидации по строкам;
  - `globalErrors` — ошибки уровня файла/лимитов;
  - `totalRows` — количество обработанных непустых строк после заголовка.

Поддерживаемые драйверы:
- `ImportDriver::CSV`;
- `ImportDriver::XLSX`.

Экспорт:
- `SpreadsheetWriter::write(...)` принимает строки и возвращает содержимое файла (`CSV` или `XLSX`) как строку;
- результат можно отдать в HTTP-ответ с `Content-Disposition: attachment`.
