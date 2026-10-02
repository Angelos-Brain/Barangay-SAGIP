<?php

namespace App\Enums;

/**
 * Where a responder account stands in First Login onboarding. Derived from
 * the user's timestamps, never stored: opening the emailed link
 * (`email_verified_at`) moves it out of `Unclaimed`, and choosing a password
 * (`account_setup_completed_at`) makes it `Active`.
 */
enum PersonnelAccountStatus: string
{
    case Unclaimed = 'unclaimed';
    case EmailVerified = 'email_verified';
    case Active = 'active';

    public function label(): string
    {
        return match ($this) {
            self::Unclaimed => 'Not yet set up',
            self::EmailVerified => 'Setup in progress',
            self::Active => 'Active',
        };
    }
}
