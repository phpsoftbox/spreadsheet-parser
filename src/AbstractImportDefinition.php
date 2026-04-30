<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser;

abstract class AbstractImportDefinition implements ImportDefinitionInterface
{
    public function options(): ImportOptions
    {
        return new ImportOptions();
    }

    public function allowHeaderless(): bool
    {
        return false;
    }
}
