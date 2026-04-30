<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

enum ImportType: string
{
    case CSV  = 'csv';
    case XLS  = 'xls';
    case XLSX = 'xlsx';
}
