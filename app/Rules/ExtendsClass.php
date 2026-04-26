<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ExtendsClass implements ValidationRule
{
    public function __construct(private readonly string $parent_class) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! class_exists($value)) {
            $fail("The :attribute must be a fully-qualified class name that exists on this server.");
            return;
        }

        if (! is_subclass_of($value, $this->parent_class)) {
            $fail("The :attribute must extend or implement [{$this->parent_class}].");
        }
    }
}
