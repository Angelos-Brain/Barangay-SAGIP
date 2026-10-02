<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects an address whose inbox already belongs to another account, so
 * john.doe@gmail.com cannot be registered once johndoe@gmail.com exists.
 * The unique index on users.email_canonical backs this up at the database.
 */
class UniqueEmailInbox implements ValidationRule
{
    public function __construct(protected ?int $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $taken = User::query()
            ->where('email_canonical', GmailAddress::canonical($value))
            ->when($this->ignoreUserId !== null, fn ($query) => $query->whereKeyNot($this->ignoreUserId))
            ->exists();

        if ($taken) {
            $fail('An account already uses this email address.');
        }
    }
}
