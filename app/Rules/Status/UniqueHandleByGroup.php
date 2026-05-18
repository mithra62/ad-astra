<?php

namespace App\Rules\Status;

use App\Models\Status;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

readonly class UniqueHandleByGroup implements ValidationRule
{
    public function __construct(private array $payload)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!empty($this->payload['status_id'])) {
            $status = Status::where('id', $this->payload['status_id'])->first();
            $check = Status::where('status_group_id', $status->status_group_id)->where('handle', $value)->where('id', '!=', $status->id)->first();
            if ($check) {
                $fail("The :attribute has already been taken.");
            }
        } elseif (!empty($this->payload['group_id'])) {
            $check = Status::where('status_group_id', $this->payload['group_id'])->where('handle', $value)->first();
            if ($check) {
                $fail("The :attribute has already been taken.");
            }
        }
    }
}
