<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\StoreSosAttachmentRequest;
use App\Http\Requests\StoreSosRequest;
use App\Models\EmergencyRequest;
use App\Services\AuditLogger;
use App\Services\SosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Feature 2: SOS button.
 *
 * Two endpoints, one payload shape:
 *   store()      — the online path: file the incident and alert responders.
 *   smsFallback() — what the browser calls when the online path did not answer
 *                   inside sagip.sos.timeout_seconds; the barangay hotline is
 *                   reached over the SMS gateway instead.
 *
 * Plus two follow-ups that never sit on the dispatch path:
 *   attach()        — the resident's optional photo / voice note, uploaded
 *                     after the SOS has already gone out.
 *   clearCooldown() — a responder lifts the resident's cooldown mid-response.
 */
class SosController extends Controller
{
    public const ATTACHMENT_DISK = 'local';

    public function __construct(
        protected SosService $sosService,
        protected AuditLogger $auditLogger,
    ) {}

    public function store(StoreSosRequest $request): JsonResponse
    {
        $this->authorize(Permission::SosTrigger->value);

        $user = Auth::user();
        $retryAfter = $this->sosService->cooldownRemainingSeconds($user);

        // One SOS per cooldown window. Nothing is filed; the resident is
        // pointed at the incident that is already active.
        if ($retryAfter > 0) {
            $active = $this->sosService->recentSos($user);

            return response()->json([
                'ok' => false,
                'cooldown' => true,
                'retry_after_seconds' => $retryAfter,
                'request_id' => $active?->id,
                'redirect_url' => $active === null ? null : route('requests.show', $active),
                'message' => sprintf(
                    'Your SOS is already active and responders are on it. You can send another in %d seconds.',
                    $retryAfter,
                ),
            ], 429)->header('Retry-After', (string) $retryAfter);
        }

        $emergencyRequest = $this->sosService->trigger($user, $request->location(), $request->details(), 'online');

        // Two presses racing past the cooldown check land on one incident, and
        // only the one that actually created it alerts responders.
        $created = $emergencyRequest->wasRecentlyCreated;
        $notified = $created ? $this->sosService->notifyResponders($emergencyRequest) : 0;

        return response()->json([
            'ok' => true,
            'duplicate' => ! $created,
            'request_id' => $emergencyRequest->id,
            'status' => $emergencyRequest->status->value,
            'notified_responders' => $notified,
            'redirect_url' => route('requests.show', $emergencyRequest),
            'attachment_url' => route('sos.attach', $emergencyRequest),
            'message' => $created
                ? 'SOS sent. Responders have been alerted.'
                : 'Your SOS is already active. Responders are on it.',
        ], $created ? 201 : 200);
    }

    /**
     * The browser reaches this only after the online attempt timed out or
     * failed. If that attempt had in fact already filed an incident, the SMS is
     * attached to it rather than opening a second one — so the cooldown never
     * refuses this path.
     */
    public function smsFallback(StoreSosRequest $request): JsonResponse
    {
        $this->authorize(Permission::SosTrigger->value);

        $user = Auth::user();

        $emergencyRequest = $this->sosService->trigger($user, $request->location(), $request->details(), 'sms');

        if ($emergencyRequest->wasRecentlyCreated) {
            $this->sosService->notifyResponders($emergencyRequest);
        }

        $message = $this->sosService->sendSmsFallback($user, $emergencyRequest);

        return response()->json([
            'ok' => $message->wasSent(),
            'request_id' => $emergencyRequest->id,
            'sms_status' => $message->status,
            'sms_driver' => $message->driver,
            'recipient' => $message->recipient,
            'failure_reason' => $message->failure_reason,
            'message' => $message->wasSent()
                ? 'SOS sent to the barangay hotline by SMS.'
                : 'The SMS gateway could not be reached. Send the text from your phone using the button shown.',
        ], $message->wasSent() ? 201 : 502);
    }

    /**
     * Optional photo or voice note for an SOS the resident already sent.
     */
    public function attach(StoreSosAttachmentRequest $request, EmergencyRequest $emergencyRequest): JsonResponse
    {
        abort_unless(
            $emergencyRequest->isSos() && $emergencyRequest->resident_id === Auth::id(),
            403,
        );

        $previousPath = $emergencyRequest->attachment_path;
        $path = $request->file('attachment')->store('sos-attachments', self::ATTACHMENT_DISK);

        $emergencyRequest->update(['attachment_path' => $path]);

        if ($previousPath !== null) {
            Storage::disk(self::ATTACHMENT_DISK)->delete($previousPath);
        }

        $this->auditLogger->record(
            action: 'sos.attachment_added',
            subject: $emergencyRequest,
            after: [
                'mime_type' => $request->file('attachment')->getMimeType(),
                'size_bytes' => $request->file('attachment')->getSize(),
            ],
            description: sprintf('Attachment added to SOS #%d.', $emergencyRequest->id),
        );

        return response()->json(['ok' => true, 'message' => 'Attachment sent to responders.'], 201);
    }

    /**
     * Personnel override: lift the resident's SOS cooldown while this
     * incident is still being responded to.
     */
    public function clearCooldown(EmergencyRequest $emergencyRequest): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($emergencyRequest->isSos() && $emergencyRequest->isHandledBy($user), 403);

        if ($emergencyRequest->canRecordOutcome()) {
            throw ValidationException::withMessages([
                'cooldown' => 'The cooldown can only be cleared while the response is still active.',
            ]);
        }

        $this->sosService->clearCooldown($emergencyRequest->resident, $emergencyRequest, $user);

        return back()->with('status', 'SOS cooldown cleared. The resident can send another SOS now.');
    }
}
