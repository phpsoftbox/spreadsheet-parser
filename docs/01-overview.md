# Overview

Главный класс компонента: `PhpSoftBox\SpreadsheetParser\SpreadsheetParser`.

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
