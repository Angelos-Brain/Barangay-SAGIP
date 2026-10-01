<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A responder's password may not simply be their own mobile number (in any
 * format) or email address — the two things anyone trying to sign in as them
 * would guess first.
 */
class PasswordDiffersFromContactDetails implements ValidationRule
{
    public function __construct(
        protected ?string $phoneNumber,
        protected ?string $email,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $candidate = trim($value);

        $candidatePhone = PhilippineMobileNumber::normalize($candidate);

        $matchesPhone = $this->phoneNumber !== null
            && $candidatePhone !== null
            && $candidatePhone === PhilippineMobileNumber::normalize($this->phoneNumber);

        $matchesEmail = $this->email !== null
            && strcasecmp($candidate, trim($this->email)) === 0;

        if ($matchesPhone || $matchesEmail) {
            $fail('Your password cannot be your mobile number or email address.');
        }
    }
}
