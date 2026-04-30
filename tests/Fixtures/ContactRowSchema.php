<?php

declare(strict_types=1);

namespace PhpSoftBox\SpreadsheetParser\Tests\Fixtures;

use PhpSoftBox\Request\ApiSchema;
use PhpSoftBox\Validator\Rule\FilledValidation;
use PhpSoftBox\Validator\Rule\PresentValidation;
use PhpSoftBox\Validator\Rule\StringValidation;

final class ContactRowSchema extends ApiSchema
{
    public function rules(): array
    {
        return [
            'email' => [
                new PresentValidation(),
                new FilledValidation(),
                new StringValidation()->email(),
            ],
            'name' => [
                new PresentValidation(),
                new FilledValidation(),
                new StringValidation(),
            ],
            'phone' => [
                new StringValidation()->nullable(),
            ],
        ];
    }
}
