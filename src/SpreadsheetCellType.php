<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

enum SpreadsheetCellType: string
{
    case Auto    = 'auto';
    case Text    = 'text';
    case Number  = 'number';
    case Boolean = 'boolean';
}
