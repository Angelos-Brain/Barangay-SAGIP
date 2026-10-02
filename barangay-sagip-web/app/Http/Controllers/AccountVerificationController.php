<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\User;
use App\Notifications\AccountVerificationUpdated;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Feature 1: Account Verification.
 *
 * Officials and administrators move resident accounts through
 * pending → verified | rejected. Until an account is verified it cannot use the
 * emergency features (see EnsureAccountVerified).
 */
class AccountVerificationController extends Controller
{
    public function __construct(protected AuditLogger $auditLogger) {}

    /**
     * The resident-facing holding page shown while an account is under review.
     */
    public function pending(): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user->isVerified()) {
            return redirect()->route('dashboard');
        }

        return view('account.verification-pending', ['user' => $user]);
    }

    public function index(Request $request): View
    {
        $this->authorize(Permission::AccountsVerify->value);

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', VerificationStatus::values())],
        ]);

        $status = VerificationStatus::tryFrom($filters['status'] ?? '') ?? VerificationStatus::Pending;

        $accounts = User::with('residentProfile')
            ->where('role', UserRole::Resident->value)
            ->where('verification_status', $status->value)
            ->orderBy('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('verifications.index', [
            'accounts' => $accounts,
            'status' => $status,
            'counts' => User::where('role', UserRole::Resident->value)
                ->selectRaw('verification_status, count(*) as total')
                ->groupBy('verification_status')
                ->pluck('total', 'verification_status'),
        ]);
    }

    public function update(User $user, Request $request): RedirectResponse
    {
        $this->authorize(Permission::AccountsVerify->value);

        $validated = $request->validate([
            'decision' => ['required', 'in:verified,rejected,pending'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        abort_if($user->isStaff(), 422, 'Staff accounts are provisioned by the barangay and are not verified here.');

        $decision = VerificationStatus::from($validated['decision']);
        $previous = $user->verification_status;

        $user->update([
            'verification_status' => $decision,
            'verified_at' => $decision === VerificationStatus::Verified ? now() : null,
            'verified_by' => Auth::id(),
            'verification_note' => $validated['note'] ?? null,
        ]);

        $this->auditLogger->record(
            action: 'account.verification_'.$decision->value,
            subject: $user,
            before: ['verification_status' => $previous?->value],
            after: ['verification_status' => $decision->value],
            description: sprintf('%s marked as %s.', $user->name, $decision->label()),
        );

        if ($previous !== $decision) {
            $user->notify(new AccountVerificationUpdated($decision, $validated['note'] ?? null));
        }

        return back()->with('status', sprintf('%s is now %s.', $user->name, $decision->label()));
    }
}
