<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\DeliverableEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handles resident account creation and captures the resident's complete
     * home address as the required registration address.
     */
    public function store(Request $request): RedirectResponse
    {
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
                'unique:users,email',
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

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('residents.profile.edit')
            ->with('status', 'Account created! Your home address was saved. Please complete the rest of your resident profile.');
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
