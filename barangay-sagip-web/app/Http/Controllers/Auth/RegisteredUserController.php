<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ResidentAwaitingVerification;
use App\Rules\DeliverableEmail;
use App\Rules\GmailAddress;
use App\Rules\UniqueEmailInbox;
use App\Services\EmailLinkService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(protected EmailLinkService $links) {}

    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handles resident account creation and captures the resident's complete
     * home address as the required registration address. The account starts
     * unverified and is not signed in until the emailed link is opened.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => GmailAddress::normalize((string) $request->input('email'))]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100', 'regex:/^\p{Lu}[\p{L}\'-]*(\s\p{Lu}[\p{L}\'-]*)*$/u'],
            'middle_name' => ['nullable', 'string', 'max:100', 'regex:/^(\p{Lu}[\p{L}\'-]*(\s\p{Lu}[\p{L}\'-]*)*)?$/u'],
            'last_name' => ['required', 'string', 'max:100', 'regex:/^\p{Lu}[\p{L}\'-]*(\s\p{Lu}[\p{L}\'-]*)*$/u'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'max:255',
                $this->emailFormatRule(),
                new DeliverableEmail,
                new GmailAddress,
                new UniqueEmailInbox,
            ],
            'phone_number' => ['required', 'string', 'max:30'],
            'address' => [
                'required',
                'string',
                'max:500',
                'regex:/^\s*(?:house\s+|unit\s+|#\s*)?\d+[A-Za-z]?(?:[-\/]\d+[A-Za-z0-9]*)?\s*,\s*[^,\s][^,]*\s*,\s*[^,\s][^,]*\s*,\s*[^,\s][^,]*\s*,\s*[^,\s][^,]*\s*$/iu',
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'first_name.regex' => 'First name must start with a capital letter and contain only letters.',
            'middle_name.regex' => 'Middle name must start with a capital letter and contain only letters.',
            'last_name.regex' => 'Last name must start with a capital letter and contain only letters.',
            'address.regex' => 'Address must follow: House/Unit Number, Street/Road, Barangay, Municipality/City, Province. Example: 123, Sample Street, Calatagan Tibang, Virac, Catanduanes.',
            'email.email' => 'Enter a valid email address, for example juan.delacruz@gmail.com.',
        ]);

        $fullName = trim(implode(' ', array_filter([
            $validated['first_name'],
            $validated['middle_name'] ?? null,
            $validated['last_name'],
        ])));

        $user = DB::transaction(function () use ($validated, $fullName) {
            $user = User::create([
                'name' => $fullName,
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'password' => Hash::make($validated['password']),
                'role' => UserRole::Resident,
            ]);

            $user->residentProfile()->create([
                'full_name' => $fullName,
                'address' => trim($validated['address']),
                'household_members_count' => 1,
            ]);

            return $user;
        });

        event(new Registered($user));

        Notification::send(
            User::whereIn('role', [UserRole::Official->value, UserRole::Admin->value])->get(),
            new ResidentAwaitingVerification($user),
        );

        return EmailVerificationController::redirectToNotice(
            $request,
            $user,
            $this->links->send($user)
                ? 'Account created! Open the link we emailed you to verify your account.'
                : 'Account created, but we could not send the verification email right now. Use the button below to send it again.',
        );
    }

    /**
     * Feature 6: strict RFC parsing plus homograph-spoofing protection, and a
     * live MX lookup when `sagip.registration.email.verify_mx` is enabled.
     */
    protected function emailFormatRule(): Rules\Email
    {
        $rule = Rule::email()->strict()->preventSpoofing();

        if (config('sagip.registration.email.verify_mx')) {
            $rule->validateMxRecord();
        }

        return $rule;
    }
}
