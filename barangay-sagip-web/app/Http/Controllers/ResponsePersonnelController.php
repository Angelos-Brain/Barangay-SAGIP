<?php

namespace App\Http\Controllers;

use App\Enums\Specialization;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\ResponsePersonnel;
use App\Models\User;
use App\Rules\GmailAddress;
use App\Rules\PhilippineMobileNumber;
use App\Rules\UniqueEmailInbox;
use App\Services\EmailLinkService;
use App\Services\TanodDutyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Feature 9: Response Personnel Management.
 * Restricted to officials via the 'role:official' middleware in routes/web.php.
 */
class ResponsePersonnelController extends Controller
{
    public function __construct(
        protected TanodDutyService $dutyService,
        protected EmailLinkService $links,
    ) {}

    public function index(): View
    {
        $personnel = ResponsePersonnel::with('user')->withCount('activeAssignments')->orderBy('name')->get();

        return view('personnel.index', ['personnel' => $personnel]);
    }

    public function create(): View
    {
        return view('personnel.create', ['specializations' => Specialization::options()]);
    }

    /**
     * Adding a responder also opens their (unclaimed) login account and emails
     * them a verification link. Opening it claims the account, and they choose
     * their own password there — none is ever set or handed over.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->normalizePhoneInput($request);
        $request->merge(['email' => GmailAddress::normalize((string) $request->input('email'))]);

        $validated = $request->validate($this->specializationRules() + [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', new GmailAddress, new UniqueEmailInbox],
            'phone_number' => ['required', 'string', new PhilippineMobileNumber, 'unique:users,phone_number'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ], [
            'phone_number.unique' => 'This mobile number is already used by another account.',
        ]);

        $personnel = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                // Unclaimed: an unguessable placeholder hash nobody knows keeps
                // password login closed until First Login sets a real one.
                'password' => Hash::make(Str::random(64)),
                'role' => UserRole::forSpecializations($validated['specializations']),
                'verification_status' => VerificationStatus::Verified,
            ]);

            return ResponsePersonnel::create(Arr::except($validated, ['email']) + [
                'user_id' => $user->id,
                'is_available' => true,
                'last_location_update' => now(),
            ]);
        });

        return redirect()->route('personnel.index')->with('status', $this->links->send($personnel->user)
            ? "{$personnel->name} added. A setup link was emailed to {$personnel->user->email}."
            : "{$personnel->name} added, but the setup email could not be sent. Tell them to request a new link at /personnel/setup with {$personnel->user->email}.");
    }

    public function edit(ResponsePersonnel $personnel): View
    {
        return view('personnel.edit', [
            'personnel' => $personnel,
            'specializations' => Specialization::options(),
        ]);
    }

    public function update(ResponsePersonnel $personnel, Request $request): RedirectResponse
    {
        $this->normalizePhoneInput($request);

        // A responder with a login keeps a valid mobile number on file.
        $validated = $request->validate($this->specializationRules() + [
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => $personnel->user_id === null
                ? ['nullable', 'string', 'max:30']
                : [
                    'sometimes',
                    'required',
                    'string',
                    new PhilippineMobileNumber,
                    Rule::unique('users', 'phone_number')->ignore($personnel->user_id),
                ],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'is_available' => ['sometimes', 'boolean'],
        ]);

        $validated['is_available'] = $request->boolean('is_available');
        $validated['last_location_update'] = now();

        if ($validated['is_available']) {
            // Feature 7: a tanod's active status is geofenced whoever sets it.
            $this->dutyService->assertMayBeMarkedAvailable($personnel);
            $validated['unavailability_reason'] = null;
        }

        DB::transaction(function () use ($personnel, $validated) {
            $personnel->update($validated);

            if (array_key_exists('phone_number', $validated)) {
                $personnel->user?->update(['phone_number' => $validated['phone_number']]);
            }
        });

        return redirect()->route('personnel.index')->with('status', "{$personnel->name} updated.");
    }

    public function destroy(ResponsePersonnel $personnel): RedirectResponse
    {
        if ($personnel->activeAssignments()->exists()) {
            return back()->with('status', "Can't delete {$personnel->name} — they have an active assignment. Resolve or reassign it first.");
        }

        $name = $personnel->name;
        $personnel->delete();

        return redirect()->route('personnel.index')->with('status', "{$name} removed.");
    }

    public function toggleAvailability(ResponsePersonnel $personnel): RedirectResponse
    {
        if (! $personnel->is_available) {
            // Feature 7: turning a tanod on requires their own on-site check-in.
            $this->dutyService->assertMayBeMarkedAvailable($personnel);
        }

        $personnel->update([
            'is_available' => ! $personnel->is_available,
            'unavailability_reason' => null,
        ]);

        return back()->with('status', "Marked {$personnel->name} as ".($personnel->is_available ? 'available' : 'unavailable').'.');
    }

    /**
     * Update the authenticated personnel's own live location.
     * The personnel ID is deliberately not accepted from the request.
     */
    public function updateLocation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $personnel = ResponsePersonnel::where('user_id', Auth::id())->firstOrFail();

        $personnel->update($validated + ['last_location_update' => now()]);

        return back()->with('status', 'Location updated.');
    }

    /**
     * Let the authenticated personnel mark themselves available or
     * unavailable, optionally explaining why they are unavailable.
     * The personnel ID is deliberately not accepted from the request.
     */
    public function updateOwnAvailability(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'is_available' => ['required', 'boolean'],
            'unavailability_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $personnel = ResponsePersonnel::where('user_id', Auth::id())->firstOrFail();
        $isAvailable = (bool) $validated['is_available'];

        if ($isAvailable) {
            // Feature 7: a tanod goes active through the geofenced check-in
            // endpoint, which is the only path that carries coordinates.
            $this->dutyService->assertMayBeMarkedAvailable($personnel);
        }

        $personnel->update([
            'is_available' => $isAvailable,
            'unavailability_reason' => $isAvailable ? null : ($validated['unavailability_reason'] ?? null),
        ]);

        return back()->with('status', $isAvailable
            ? 'You are now available for new assignments.'
            : 'You are now unavailable and will not receive new assignments.');
    }

    /**
     * Feature 10: responders may pick their own specialization tags. The tags
     * decide which incidents they are alerted about (Feature 8) and which
     * requests they see (Feature 3).
     */
    public function editOwnSpecializations(): View
    {
        $personnel = ResponsePersonnel::where('user_id', Auth::id())->firstOrFail();

        return view('personnel.specializations', [
            'personnel' => $personnel,
            'specializations' => Specialization::options(),
        ]);
    }

    public function updateOwnSpecializations(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->specializationRules());

        $personnel = ResponsePersonnel::where('user_id', Auth::id())->firstOrFail();
        $personnel->update($validated);

        return redirect()->route('personnel.specializations.edit')
            ->with('status', 'Your specializations are now: '.$personnel->specializationLabels());
    }

    /**
     * Store mobile numbers in one form (09XXXXXXXXX) so uniqueness checks and
     * the mobile-number login match however the official typed it.
     */
    protected function normalizePhoneInput(Request $request): void
    {
        $phone = $request->input('phone_number');

        if (is_string($phone) && ($normalized = PhilippineMobileNumber::normalize($phone)) !== null) {
            $request->merge(['phone_number' => $normalized]);
        }
    }

    /**
     * Validation rules for the specialization tag multi-select. At least one
     * tag is required, since `response_personnel.specialization` is not
     * nullable and dispatch routing depends on it.
     *
     * @return array<string, list<string>>
     */
    protected function specializationRules(): array
    {
        return [
            'specializations' => ['required', 'array', 'min:1'],
            'specializations.*' => ['string', 'in:'.implode(',', Specialization::values())],
        ];
    }
}
