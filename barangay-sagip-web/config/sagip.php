<?php

/**
 * Barangay SAGIP operational settings.
 *
 * These values are deployment-specific (the barangay hall's real coordinates,
 * how far a tanod may stray before a check-in is rejected, how long the SOS
 * button waits before falling back to SMS) so each one is env-overridable.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Barangay hall
    |--------------------------------------------------------------------------
    |
    | Reference point for the tanod check-in geofence. Defaults to the Virac,
    | Catanduanes coordinates already used as the demo seeder's base point.
    |
    */
    'hall' => [
        'name' => env('SAGIP_HALL_NAME', 'Barangay Hall'),
        'latitude' => (float) env('SAGIP_HALL_LATITUDE', 13.5920),
        'longitude' => (float) env('SAGIP_HALL_LONGITUDE', 124.2050),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tanod check-in geofence
    |--------------------------------------------------------------------------
    |
    | A tanod may only go on duty while standing within this many metres of
    | the hall coordinates above.
    |
    */
    'tanod' => [
        'check_in_radius_meters' => (int) env('SAGIP_TANOD_RADIUS_METERS', 150),
    ],

    /*
    |--------------------------------------------------------------------------
    | SOS button
    |--------------------------------------------------------------------------
    |
    | `timeout_seconds` is how long the browser waits for the SOS endpoint to
    | acknowledge before it switches to the SMS fallback. `cooldown_seconds`
    | stops a panicking resident from filing a dozen duplicate incidents.
    |
    | `hold_milliseconds` and `cancel_window_seconds` are the anti-false-alarm
    | friction: press-and-hold, then a countdown the resident can cancel.
    | Incidents with GPS accuracy worse than `poor_accuracy_meters`, or farther
    | than `bounds_radius_meters` from the hall, are flagged — never blocked.
    | An account with `false_alarm_flag_threshold` or more False Alarm
    | outcomes is listed for admin review; it is never suspended automatically.
    |
    */
    'sos' => [
        'timeout_seconds' => (int) env('SAGIP_SOS_TIMEOUT_SECONDS', 8),
        'cooldown_seconds' => (int) env('SAGIP_SOS_COOLDOWN_SECONDS', 120),
        'hotline_number' => env('SAGIP_SOS_HOTLINE_NUMBER', '09170000001'),
        'hold_milliseconds' => (int) env('SAGIP_SOS_HOLD_MILLISECONDS', 2000),
        'cancel_window_seconds' => (int) env('SAGIP_SOS_CANCEL_WINDOW_SECONDS', 5),
        'poor_accuracy_meters' => (int) env('SAGIP_SOS_POOR_ACCURACY_METERS', 100),
        'bounds_radius_meters' => (int) env('SAGIP_SOS_BOUNDS_RADIUS_METERS', 3000),
        'false_alarm_flag_threshold' => (int) env('SAGIP_SOS_FALSE_ALARM_THRESHOLD', 3),
        'attachment_max_kilobytes' => (int) env('SAGIP_SOS_ATTACHMENT_MAX_KB', 10240),
    ],

    /*
    |--------------------------------------------------------------------------
    | Personnel mobile-number login
    |--------------------------------------------------------------------------
    |
    | Responders added by an official sign in with their mobile number and a
    | one-time code sent by SMS — there is no password to set or change. A code
    | expires after `code_ttl_minutes`, is void after `max_attempts` wrong
    | guesses, and a new one can be requested every `resend_seconds`.
    |
    */
    'personnel_login' => [
        'code_ttl_minutes' => (int) env('SAGIP_LOGIN_CODE_TTL_MINUTES', 5),
        'max_attempts' => (int) env('SAGIP_LOGIN_CODE_MAX_ATTEMPTS', 5),
        'resend_seconds' => (int) env('SAGIP_LOGIN_CODE_RESEND_SECONDS', 60),
        'max_login_attempts' => (int) env('SAGIP_PERSONNEL_MAX_LOGIN_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('SAGIP_PERSONNEL_LOCKOUT_MINUTES', 15),
        'email_link_ttl_hours' => (int) env('SAGIP_PERSONNEL_EMAIL_LINK_TTL_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Emailed links (verification and password reset)
    |--------------------------------------------------------------------------
    |
    | Residents and personnel prove they own their Gmail address by opening a
    | single-use link; personnel reset a forgotten password the same way. A
    | link expires after `link_ttl_hours`, and a new one can be requested
    | every `resend_seconds`.
    |
    */
    'email_links' => [
        'link_ttl_hours' => (int) env('SAGIP_EMAIL_LINK_TTL_HOURS', 24),
        'resend_seconds' => (int) env('SAGIP_EMAIL_LINK_RESEND_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS gateway
    |--------------------------------------------------------------------------
    |
    | `log` writes the message to the application log and is the default for
    | local development and tests. `semaphore` posts to the Semaphore.co API
    | using Laravel's HTTP client — no extra Composer package is required.
    |
    */
    'sms' => [
        'driver' => env('SAGIP_SMS_DRIVER', 'log'),

        'semaphore' => [
            'endpoint' => env('SAGIP_SEMAPHORE_ENDPOINT', 'https://api.semaphore.co/api/v4/messages'),
            'api_key' => env('SAGIP_SEMAPHORE_API_KEY'),
            'sender_name' => env('SAGIP_SEMAPHORE_SENDER_NAME'),
            'timeout' => (int) env('SAGIP_SEMAPHORE_TIMEOUT', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    |
    | Feature 6: no account is created without a syntactically valid,
    | deliverable-looking email address. `verify_mx` adds a live DNS lookup for
    | the domain's mail records; it is off by default so local development and
    | the test suite do not depend on network access, and should be switched on
    | in production.
    |
    */
    'registration' => [
        'email' => [
            'verify_mx' => (bool) env('SAGIP_VERIFY_EMAIL_MX', false),

            /*
             * Throwaway-mailbox providers. Subdomains are matched too, so
             * `mailinator.com` also blocks `anything.mailinator.com`.
             */
            'disposable_domains' => [
                '0-mail.com', '10minutemail.com', '1secmail.com', '20minutemail.com',
                'burnermail.io', 'dispostable.com', 'discard.email', 'emailondeck.com',
                'fakeinbox.com', 'getairmail.com', 'getnada.com', 'grr.la',
                'guerrillamail.com', 'guerrillamailblock.com', 'harakirimail.com',
                'inboxbear.com', 'mailcatch.com', 'maildrop.cc', 'mailinator.com',
                'mailnesia.com', 'mailsac.com', 'mintemail.com', 'moakt.com',
                'mytemp.email', 'pokemail.net', 'sharklasers.com', 'spam4.me',
                'spamgourmet.com', 'temp-mail.org', 'tempinbox.com', 'tempmail.com',
                'tempr.email', 'throwawaymail.com', 'tmail.ws', 'trashmail.com',
                'yopmail.com',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Incident hotspots
    |--------------------------------------------------------------------------
    |
    | Incidents are grouped into a coarse geographic grid; a cell becomes a
    | hotspot once it holds at least `min_incidents` reports from the last
    | `recency_days` days.
    |
    */
    'hotspots' => [
        'grid_precision' => (int) env('SAGIP_HOTSPOT_GRID_PRECISION', 3),
        'recency_days' => (int) env('SAGIP_HOTSPOT_RECENCY_DAYS', 30),
        'min_incidents' => (int) env('SAGIP_HOTSPOT_MIN_INCIDENTS', 2),
    ],

];
