<?php

namespace AdAstra\Http\Requests\User;

use AdAstra\Enums\UserStatus;
use AdAstra\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('manage user status');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(UserStatus::ALL)],
            'reason' => [
                'required_unless:status,' . UserStatus::ACTIVE,
                'nullable',
                'string',
                'max:500',
            ],
            'suspended_until' => [
                'nullable',
                'date',
                'after:now',
                'required_if:status,' . UserStatus::SUSPENDED,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'The selected status is not valid.',
            'reason.required_unless' => 'A reason is required when changing to this status.',
            'suspended_until.required_if' => 'A suspension end date is required.',
            'suspended_until.after' => 'The suspension end date must be in the future.',
        ];
    }
}
