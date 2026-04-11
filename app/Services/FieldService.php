<?php

namespace App\Services;

class FieldService extends AbstractService
{

    public function getFieldTypes(): array
    {
        return [
            'text' => 'Text',
            'textarea' => 'Textarea',
        ];
    }
}
