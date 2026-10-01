<?php

namespace App\Http\Controllers;

use App\Models\EmergencyRequest;
use App\Models\ResponsePersonnel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Feature 11: Dashboards.
 *
 * Officials and personnel land here after login. Residents never see this
 * view — they're sent straight to the report form (requests.create) since
 * that's the resident-facing app's primary action; "My Requests" covers
 * what the old resident dashboard branch used to show.
 */
class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user->isResident()) {
            return redirect()->route('requests.create');
        }

        if ($user->isPersonnel()) {
            $personnel = $user->responsePersonnel;
            $assignments = $personnel
                ? $personnel->assignments()->with('emergencyRequest')->whereNull('completed_at')->get()
                : collect();

            return view('dashboard', [
                'role' => 'personnel',
                'personnel' => $personnel,
                'assignments' => $assignments,
            ]);
        }

        // Official / barangay-wide overview
        $counts = [
            'total' => EmergencyRequest::count(),
            'needs_review' => EmergencyRequest::where('needs_review', true)
                ->where('status', 'needs_review')
                ->count(),
            'critical_open' => EmergencyRequest::where('urgency', 'critical')
                ->whereNotIn('status', ['resolved', 'cancelled'])
                ->count(),
            'resolved_today' => EmergencyRequest::where('status', 'resolved')
                ->whereDate('updated_at', today())
                ->count(),
            'available_personnel' => ResponsePersonnel::where('is_available', true)->count(),
        ];

        $categoryBreakdown = EmergencyRequest::selectRaw('category, count(*) as total')
            ->whereNotNull('category')
            ->groupBy('category')
            ->pluck('total', 'category');

        $needsReviewRequests = EmergencyRequest::with(['resident'])
            ->where('needs_review', true)
            ->where('status', 'needs_review')
            ->latest()
            ->limit(10)
            ->get();

        $recentRequests = EmergencyRequest::with(['resident', 'currentAssignment.responsePersonnel'])
            ->latest()
            ->limit(15)
            ->get();

        // Feature 2: accounts with repeat false-alarm outcomes, for human review.
        $flaggedAccounts = User::flaggedForFalseAlarms()
            ->orderByDesc('false_alarm_count')
            ->limit(20)
            ->get();

        return view('dashboard', [
            'role' => 'official',
            'counts' => $counts,
            'categoryBreakdown' => $categoryBreakdown,
            'needsReviewRequests' => $needsReviewRequests,
            'recentRequests' => $recentRequests,
            'flaggedAccounts' => $flaggedAccounts,
        ]);
    }
}
