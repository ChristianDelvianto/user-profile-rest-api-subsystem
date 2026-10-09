<?php

namespace App\Http\Requests\v1\Auth;

use App\Rules\AlphaNumSpaces;
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
        $userUsernameMaxLength = config('custom.user.username.max_length');
        $userUsernameMinLength = config('custom.user.username.min_length');
        $userNameMaxLength = config('custom.user.name.max_length');
        $userNameMinLength = config('custom.user.name.min_length');

        return [
            'username' => [
                'required',
                'string',
                "max:{$userUsernameMaxLength}",
                "min:{$userUsernameMinLength}"
            ],
            'name' => [
                'required',
                'string',
                "max:{$userNameMaxLength}",
                "min:{$userNameMinLength}",
                new AlphaNumSpaces
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
