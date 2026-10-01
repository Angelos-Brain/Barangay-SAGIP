<?php

namespace App\Enums;

/**
 * Where a responder account stands in First Login onboarding. Derived from
 * the user's timestamps, never stored: `phone_verified_at` moves it out of
 * `Unclaimed`, and it only becomes `Active` once a password is set
 * (`account_setup_completed_at`) and the email link is confirmed
 * (`email_verified_at`).
 */
enum PersonnelAccountStatus: string
{
    case Unclaimed = 'unclaimed';
    case PhoneVerified = 'phone_verified';
    case Active = 'active';

    public function label(): string
    {
        return match ($this) {
            self::Unclaimed => 'Not yet set up',
            self::PhoneVerified => 'Setup in progress',
            self::Active => 'Active',
        };
    }
}
