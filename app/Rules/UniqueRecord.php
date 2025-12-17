<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueRecord implements ValidationRule
{
    public function __construct(
        protected ?int $table,
        protected int $groupId,
        protected ?int $parentId
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        echo $attribute;
        exit;
    }
}
