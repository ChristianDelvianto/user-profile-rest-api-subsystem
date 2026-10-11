<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UsernameRegex implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Enforces alphanumeric, dots, and underscores only.
        // For real production, the rule will be stricter.
        if (! is_string($value) || ! preg_match('/^[a-zA-Z0-9._]+$/', $value)) {
            $fail("The :attribute may only contain letters, numbers, dots, and underscores.");
        }
    }
}
