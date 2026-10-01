<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Feature 6: Email Validation.
 *
 * Complements Laravel's RFC check with the parts it deliberately allows but a
 * barangay roster cannot use: throwaway mailbox providers, hostnames with no
 * public TLD, and the malformed shapes (doubled dots, dot-edged local parts,
 * hyphen-edged domain labels) that pass a permissive parse but never deliver.
 */
class DeliverableEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail('Enter a valid email address.');

            return;
        }

        $email = strtolower(trim($value));

        if (substr_count($email, '@') !== 1) {
            $fail('Enter a valid email address, for example juan.delacruz@gmail.com.');

            return;
        }

        [$local, $domain] = explode('@', $email, 2);

        if ($this->hasMalformedShape($local, $domain)) {
            $fail('That email address is not formatted correctly. Check for extra dots or missing characters.');

            return;
        }

        if (! $this->hasPublicTld($domain)) {
            $fail('Enter an email address with a real domain, for example gmail.com.');

            return;
        }

        if ($this->isDisposable($domain)) {
            $fail('Temporary or disposable email addresses are not accepted. Use a personal or work email address.');
        }
    }

    protected function hasMalformedShape(string $local, string $domain): bool
    {
        if ($local === '' || $domain === '') {
            return true;
        }

        if (str_contains($local, '..') || str_contains($domain, '..')) {
            return true;
        }

        if (str_starts_with($local, '.') || str_ends_with($local, '.')) {
            return true;
        }

        foreach (explode('.', $domain) as $label) {
            if ($label === '' || str_starts_with($label, '-') || str_ends_with($label, '-')) {
                return true;
            }

            if (preg_match('/^[a-z0-9-]+$/', $label) !== 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * A deliverable address needs a dotted domain whose last label is an
     * alphabetic TLD of at least two characters — this rejects `user@localhost`
     * and `user@192.168.1.10` alike.
     */
    protected function hasPublicTld(string $domain): bool
    {
        if (! str_contains($domain, '.')) {
            return false;
        }

        $labels = explode('.', $domain);
        $tld = end($labels);

        return preg_match('/^[a-z]{2,}$/', $tld) === 1;
    }

    protected function isDisposable(string $domain): bool
    {
        /** @var list<string> $blocked */
        $blocked = config('sagip.registration.email.disposable_domains', []);

        foreach ($blocked as $blockedDomain) {
            $blockedDomain = strtolower($blockedDomain);

            if ($domain === $blockedDomain || str_ends_with($domain, '.'.$blockedDomain)) {
                return true;
            }
        }

        return false;
    }
}
