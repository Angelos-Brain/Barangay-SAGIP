<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Philippine mobile number, accepted as 09XXXXXXXXX, 9XXXXXXXXX, 639XXXXXXXXX
 * or +639XXXXXXXXX with optional spaces or dashes, and always stored in the
 * local 09XXXXXXXXX form so a login lookup matches however it was typed.
 */
class PhilippineMobileNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::normalize($value) === null) {
            $fail('Enter a valid Philippine mobile number, e.g. 09171234567.');
        }
    }

    public static function normalize(string $value): ?string
    {
        $digits = preg_replace('/[\s\-()]/', '', $value);

        if (preg_match('/^(?:\+?63|0)?(9\d{9})$/', $digits, $matches) !== 1) {
            return null;
        }

        return '0'.$matches[1];
    }
}
