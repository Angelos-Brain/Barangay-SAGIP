<?php

namespace App\Http\Controllers;

use App\Enums\IncidentOutcome;
use App\Enums\Permission;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Http\Requests\StoreEmergencyRequestRequest;
use App\Models\EmergencyRequest;
use App\Models\ResponsePersonnel;
use App\Notifications\IncidentReported;
use App\Notifications\RequestStatusUpdated;
use App\Services\IncidentAlertService;
use App\Services\IncidentOutcomeService;
use App\Services\ResponseAssignmentService;
use App\Services\TokenizationClassificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmergencyRequestController extends Controller
{
    public function __construct(
        protected TokenizationClassificationService $mlService,
        protected ResponseAssignmentService $assignmentService,
        protected IncidentAlertService $alertService,
    ) {}

    public function create(): View
    {
        return view('requests.create');
    }

    public function store(StoreEmergencyRequestRequest $request): RedirectResponse
    {
        $emergencyRequest = new EmergencyRequest($request->validated());
        $emergencyRequest->resident_id = Auth::id();
        $emergencyRequest->status = RequestStatus::Submitted;
        $emergencyRequest->save();

        $this->mlService->classifyAndApply($emergencyRequest);
        $emergencyRequest->save();

        $emergencyRequest->statusLogs()->create([
            'status' => RequestStatus::Submitted->value,
            'note' => 'Request submitted by resident.',
            'changed_by' => Auth::id(),
        ]);

        if ($emergencyRequest->needs_review) {
            $emergencyRequest->transitionTo(
                RequestStatus::NeedsReview,
                $emergencyRequest->review_reason ?? 'Flagged for manual validation.'
            );
        } else {
            $emergencyRequest->transitionTo(RequestStatus::Validated, 'Auto-validated (high classification confidence).');
            $this->assignmentService->autoAssign($emergencyRequest->fresh());
        }

        // Auto-assignment may already have moved the request on to `assigned`,
        // so the resident is told the status it actually settled in.
        $settled = $emergencyRequest->fresh();
        Auth::user()->notify(new RequestStatusUpdated($settled, $settled->status->value));

        // Feature 8: email the officials and the on-duty responders whose
        // specialization covers this incident. Sent after classification so the
        // alert can name the category, and after the status transition so
        // recipients open the request in its settled state.
        $this->alertService->alert($settled, new IncidentReported($settled));

        return redirect()
            ->route('requests.show', $emergencyRequest)
            ->with('status', 'Your request has been submitted.');
    }

    public function show(EmergencyRequest $emergencyRequest): View
    {
        $this->authorizeView($emergencyRequest);

        $emergencyRequest->load(['statusLogs.changedByUser', 'currentAssignment.responsePersonnel.user', 'resident.residentProfile']);

        return view('requests.show', ['emergencyRequest' => $emergencyRequest]);
    }

    public function index(): View
    {
        $user = Auth::user();

        $query = EmergencyRequest::with(['resident', 'currentAssignment.responsePersonnel']);

        if ($user->isResident()) {
            $query->where('resident_id', $user->id);
        } elseif ($user->role === UserRole::Personnel) {
            // The generic responder role stays scoped to its own assignments.
            $query->whereHas('currentAssignment.responsePersonnel', function ($assignmentQuery) use ($user) {
                $assignmentQuery->where('user_id', $user->id);
            });
        } elseif ($user->isPersonnel()) {
            // Feature 3: a specialized responder sees the requests assigned to
            // them plus any incident falling inside their specialization tags.
            $categories = $user->incidentCategories();

            $query->where(function ($scoped) use ($user, $categories) {
                $scoped->whereHas('currentAssignment.responsePersonnel', function ($assignmentQuery) use ($user) {
                    $assignmentQuery->where('user_id', $user->id);
                });

                if ($categories !== []) {
                    $scoped->orWhereIn('category', $categories);
                }
            });
        }

        $requests = $query
            ->orderByRaw("CASE urgency\n                WHEN 'critical' THEN 4\n                WHEN 'high' THEN 3\n                WHEN 'average' THEN 2\n                WHEN 'low' THEN 1\n                ELSE 0\n            END DESC")
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('requests.index', ['requests' => $requests]);
    }

    /**
     * Officials/personnel manually advance a request's status.
     * Resolving or cancelling an active request also closes its assignment
     * and releases the responder's workload slot.
     */
    public function updateStatus(EmergencyRequest $emergencyRequest, Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user->hasPermission(Permission::RequestsUpdateStatus), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:validated,assigned,en_route,resolved,cancelled'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $newStatus = RequestStatus::from($validated['status']);

        if ($user->isPersonnel() && $newStatus === RequestStatus::Validated) {
            throw ValidationException::withMessages([
                'status' => 'Only an official can validate a request flagged for review.',
            ]);
        }

        DB::transaction(function () use ($emergencyRequest, $newStatus, $validated, $user) {
            $lockedRequest = EmergencyRequest::whereKey($emergencyRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedRequest->canTransitionTo($newStatus)) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'Request cannot transition from %s to %s.',
                        $lockedRequest->status->label(),
                        $newStatus->label()
                    ),
                ]);
            }

            $assignment = null;

            if ($user->isPersonnel()) {
                $personnel = $user->responsePersonnel()->first();

                $assignment = $lockedRequest->currentAssignment()->lockForUpdate()->first();

                if ($personnel === null || $assignment === null || $assignment->response_personnel_id !== $personnel->id) {
                    throw ValidationException::withMessages([
                        'status' => 'You can only update the status of a request assigned to you.',
                    ]);
                }
            }

            if (in_array($newStatus, [RequestStatus::Resolved, RequestStatus::Cancelled], true)) {
                $assignment ??= $lockedRequest->currentAssignment()->lockForUpdate()->first();

                if ($assignment !== null) {
                    $personnel = $assignment->responsePersonnel()->lockForUpdate()->first();

                    $assignment->update(['completed_at' => now()]);

                    if ($personnel !== null) {
                        ResponsePersonnel::whereKey($personnel->id)
                            ->where('current_workload', '>', 0)
                            ->decrement('current_workload');
                    }
                }
            }

            $lockedRequest->transitionTo(
                $newStatus,
                $validated['note'] ?? null,
                Auth::id()
            );
        });

        $emergencyRequest->refresh();
        $emergencyRequest->resident->notify(
            new RequestStatusUpdated($emergencyRequest, $newStatus->value)
        );

        // The assigned responder hears about changes someone else made to their
        // request (e.g. an official cancelling it). Resolving or cancelling
        // closes the assignment, so the latest one is used.
        $responder = $emergencyRequest->assignments()->latest('id')->first()?->responsePersonnel?->user;

        if ($responder !== null && $responder->isNot($user) && $responder->isNot($emergencyRequest->resident)) {
            $responder->notify(new RequestStatusUpdated($emergencyRequest, $newStatus->value, forResponder: true));
        }

        return back()->with('status', 'Status updated.');
    }

    /**
     * Feature 2: post-incident validation. Once the response is over, the
     * assigned responder or an official records whether it was real.
     */
    public function updateOutcome(
        EmergencyRequest $emergencyRequest,
        Request $request,
        IncidentOutcomeService $outcomeService,
    ): RedirectResponse {
        $user = Auth::user();
        abort_unless($emergencyRequest->isHandledBy($user), 403);

        $validated = $request->validate([
            'outcome' => ['required', Rule::enum(IncidentOutcome::class)],
        ]);

        if (! $emergencyRequest->canRecordOutcome()) {
            throw ValidationException::withMessages([
                'outcome' => 'Record the outcome after the request is resolved or cancelled.',
            ]);
        }

        $outcomeService->record($emergencyRequest, IncidentOutcome::from($validated['outcome']), $user);

        return back()->with('status', 'Outcome recorded.');
    }

    /**
     * Feature 2: the optional photo / voice note the resident sent with an
     * SOS, streamed from private storage to anyone allowed to see the incident.
     */
    public function attachment(EmergencyRequest $emergencyRequest): StreamedResponse
    {
        $this->authorizeView($emergencyRequest);

        $disk = Storage::disk(SosController::ATTACHMENT_DISK);

        abort_if(
            $emergencyRequest->attachment_path === null || ! $disk->exists($emergencyRequest->attachment_path),
            404,
        );

        return $disk->response($emergencyRequest->attachment_path);
    }

    protected function authorizeView(EmergencyRequest $emergencyRequest): void
    {
        $user = Auth::user();

        if ($user->isOfficialOrAdmin() || $emergencyRequest->resident_id === $user->id) {
            return;
        }

        if ($user->isPersonnel()) {
            $hasActiveAssignment = $emergencyRequest->currentAssignment()
                ->whereHas('responsePersonnel', function ($personnelQuery) use ($user) {
                    $personnelQuery->where('user_id', $user->id);
                })
                ->exists();

            if ($hasActiveAssignment) {
                return;
            }

            // Feature 3: a specialized responder may open an incident inside
            // their own specialization even before it is assigned to them. The
            // generic `personnel` role stays restricted to its assignments.
            $inOwnSpecialization = $user->role !== UserRole::Personnel
                && in_array($emergencyRequest->category, $user->incidentCategories(), true);

            abort_unless($inOwnSpecialization, 403);

            return;
        }

        abort(403);
    }
}
