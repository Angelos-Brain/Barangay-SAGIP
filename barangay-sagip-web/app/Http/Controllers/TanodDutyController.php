<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\ResponsePersonnel;
use App\Services\TanodDutyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Feature 7: Tanod location lock.
 *
 * Going on duty is a check-in at the barangay hall, so it gets its own endpoint
 * rather than reusing the ordinary availability toggle — the toggle carries no
 * coordinates and could not be geofenced.
 */
class TanodDutyController extends Controller
{
    public function __construct(protected TanodDutyService $dutyService) {}

    public function checkIn(Request $request): RedirectResponse
    {
        $this->authorize(Permission::TanodCheckIn->value);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ], [
            'latitude.required' => 'Your location could not be read. Turn on GPS and try again.',
            'longitude.required' => 'Your location could not be read. Turn on GPS and try again.',
        ]);

        $personnel = $this->ownPersonnelRecord();

        // Throws a validation error, and flags the attempt, when out of range.
        $result = $this->dutyService->checkIn(
            $personnel,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
        );

        return back()->with('status', sprintf(
            'You are on duty. Checked in %sm from the barangay hall.',
            number_format($result['distance']),
        ));
    }

    public function checkOut(Request $request): RedirectResponse
    {
        $this->authorize(Permission::TanodCheckIn->value);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->dutyService->checkOut($this->ownPersonnelRecord(), $validated['reason'] ?? null);

        return back()->with('status', 'You are now off duty and will not receive new assignments.');
    }

    /**
     * The responder id is deliberately never accepted from the request.
     */
    protected function ownPersonnelRecord(): ResponsePersonnel
    {
        return ResponsePersonnel::where('user_id', Auth::id())->firstOrFail();
    }
}
