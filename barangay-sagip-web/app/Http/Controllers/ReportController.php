<?php

namespace App\Http\Controllers;

use App\Models\EmergencyRequest;
use App\Models\ResponseAssignment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Feature 12: Report Generator.
 * Summary dashboard view plus a CSV export officials can open in Excel/Sheets
 * for the barangay's own record-keeping (no extra PDF library dependency
 * required to get a working report out the door).
 */
class ReportController extends Controller
{
    public function index(): View
    {
        $byCategory = EmergencyRequest::select('category', DB::raw('count(*) as total'))
            ->whereNotNull('category')
            ->groupBy('category')
            ->pluck('total', 'category');

        $byUrgency = EmergencyRequest::select('urgency', DB::raw('count(*) as total'))
            ->whereNotNull('urgency')
            ->groupBy('urgency')
            ->pluck('total', 'urgency');

        $avgResponseMinutes = ResponseAssignment::whereNotNull('completed_at')
            ->selectRaw('AVG('.$this->minutesBetween('assigned_at', 'completed_at').') as avg_minutes')
            ->value('avg_minutes');

        $personnelWorkload = DB::table('response_personnel')
            ->leftJoin('response_assignments', 'response_personnel.id', '=', 'response_assignments.response_personnel_id')
            ->select('response_personnel.name', DB::raw('count(response_assignments.id) as total_assignments'))
            ->groupBy('response_personnel.id', 'response_personnel.name')
            ->orderByDesc('total_assignments')
            ->get();

        return view('reports.index', compact('byCategory', 'byUrgency', 'avgResponseMinutes', 'personnelWorkload'));
    }

    /**
     * Minutes between two timestamp columns. Local development uses MySQL or
     * SQLite and production uses PostgreSQL, so the date arithmetic has to
     * match the active driver.
     */
    protected function minutesBetween(string $from, string $to): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "EXTRACT(EPOCH FROM ({$to} - {$from})) / 60",
            'sqlite' => "(julianday({$to}) - julianday({$from})) * 1440",
            default => "TIMESTAMPDIFF(MINUTE, {$from}, {$to})",
        };
    }

    public function exportCsv(): Response
    {
        $requests = EmergencyRequest::with('resident', 'currentAssignment.responsePersonnel')->get();

        $csv = "ID,Resident,Category,Urgency,Status,Category Confidence,Urgency Confidence,Assigned Personnel,Submitted At\n";
        foreach ($requests as $r) {
            $csv .= implode(',', [
                $r->id,
                '"'.str_replace('"', '""', $r->resident->name).'"',
                $r->category,
                $r->urgency?->value,
                $r->status->value,
                $r->category_confidence,
                $r->urgency_confidence,
                '"'.($r->currentAssignment->responsePersonnel->name ?? '').'"',
                $r->created_at->toDateTimeString(),
            ])."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="barangay_sagip_requests_'.now()->format('Y-m-d').'.csv"',
        ]);
    }
}
