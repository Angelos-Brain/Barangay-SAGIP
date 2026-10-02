<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Resident and personnel accounts must use a Gmail address, so every account
 * can receive its verification link at a mailbox we know accepts mail.
 *
 * Gmail ignores dots in the name and anything after "+", so
 * john.doe+x@gmail.com and johndoe@gmail.com reach the same inbox; canonical()
 * gives that shared form for duplicate checks. The address itself is stored
 * as the user typed it (trimmed, lowercased) and is where mail is sent.
 */
class GmailAddress implements ValidationRule
{
    public const DOMAIN = 'gmail.com';

    public const MESSAGE = 'Please use a Gmail address (example@gmail.com).';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isGmail($value)) {
            $fail(self::MESSAGE);
        }
    }

    public static function isGmail(string $email): bool
    {
        $email = self::normalize($email);

        if (substr_count($email, '@') !== 1) {
            return false;
        }

        [$local, $domain] = explode('@', $email);

        return $domain === self::DOMAIN && self::canonicalLocalPart($local) !== '';
    }

    /**
     * Trimmed and lowercased: the form an address is saved in.
     */
    public static function normalize(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * The inbox an address delivers to. For Gmail, dots and "+tags" are
     * dropped; any other address is just normalized.
     */
    public static function canonical(string $email): string
    {
        $email = self::normalize($email);

        if (substr_count($email, '@') !== 1) {
            return $email;
        }

        [$local, $domain] = explode('@', $email);

        return $domain === self::DOMAIN
            ? self::canonicalLocalPart($local).'@'.$domain
            : $email;
    }

    protected static function canonicalLocalPart(string $local): string
    {
        return str_replace('.', '', explode('+', $local, 2)[0]);
    }
}
