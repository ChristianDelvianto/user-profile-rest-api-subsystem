<?php

namespace App\Http\Requests\v1\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SignupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userMaxNameLength = config('custom.user.name.max_length');
        $userMinNameLength = config('custom.user.name.min_length');

        return [
            'name' => [
                'required',
                'string',
                "max:{$userMaxNameLength}",
                "min:{$userMinNameLength}"
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email'
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:8',
                'max:255'
            ]
        ];
    }
}
