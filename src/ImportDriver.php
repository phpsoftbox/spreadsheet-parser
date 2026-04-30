<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

enum ImportDriver: string
{
    case CSV  = 'csv';
    case XLSX = 'xlsx';
}
