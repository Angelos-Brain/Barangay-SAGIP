<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Models\EmergencyRequest;
use App\Models\ResponsePersonnel;
use App\Notifications\AssignmentReleased;
use App\Notifications\NewAssignmentNotification;
use App\Notifications\RequestStatusUpdated;
use App\Services\ResponseAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Feature 6: Response Assignment Classification — manual override path.
 * Auto-assignment lives in ResponseAssignmentService::autoAssign(), called
 * from EmergencyRequestController::store(). This controller covers the case
 * where an official wants to review the ML recommendation and reassign.
 */
class ResponseAssignmentController extends Controller
{
    public function __construct(protected ResponseAssignmentService $assignmentService) {}

    public function edit(EmergencyRequest $emergencyRequest): View
    {
        if (! in_array($emergencyRequest->status, [RequestStatus::Validated, RequestStatus::Assigned], true)) {
            throw ValidationException::withMessages([
                'response_personnel_id' => 'This request cannot be assigned in its current status.',
            ]);
        }

        $availablePersonnel = ResponsePersonnel::with('user')->where('is_available', true)
            ->whereDoesntHave('activeAssignments')
            ->orderBy('name')
            ->get();

        return view('requests.assign', [
            'emergencyRequest' => $emergencyRequest->load('currentAssignment.responsePersonnel'),
            'availablePersonnel' => $availablePersonnel,
        ]);
    }

    public function store(EmergencyRequest $emergencyRequest, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'response_personnel_id' => ['required', 'exists:response_personnel,id'],
        ]);

        $previousResponder = $emergencyRequest->currentAssignment()->first()?->responsePersonnel?->user;

        $assignment = $this->assignmentService->manualAssign(
            $emergencyRequest,
            (int) $validated['response_personnel_id'],
            Auth::id()
        );

        $assignment->responsePersonnel->user?->notify(new NewAssignmentNotification($assignment));

        if ($previousResponder !== null && $previousResponder->isNot($assignment->responsePersonnel->user)) {
            $previousResponder->notify(new AssignmentReleased($emergencyRequest));
        }

        $emergencyRequest->refresh();
        $emergencyRequest->resident?->notify(
            new RequestStatusUpdated($emergencyRequest, $emergencyRequest->status->value)
        );

        return redirect()->route('requests.show', $emergencyRequest)->with('status', 'Responder assigned.');
    }
}
