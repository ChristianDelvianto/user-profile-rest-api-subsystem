<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class AlphaNumSpaces implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Enforces alphanumeric and standard spaces only
        if (! is_string($value) || ! preg_match('/^[a-zA-Z0-9 ]+$/', $value)) {
            $fail("The :attribute may only contain letters, numbers, and spaces.");
        }
    }
}
