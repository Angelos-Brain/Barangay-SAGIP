<?php

namespace App\Http\Controllers;

use App\Enums\EvacuationCenterStatus;
use App\Enums\Permission;
use App\Models\EmergencyRequest;
use App\Models\EvacuationCenter;
use App\Models\ResponsePersonnel;
use App\Services\IncidentHotspotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Feature 8 (original): Location Map Generator.
 * Feature 4: the same map also carries the evacuation centers and the incident
 * hotspots clustered by density and recency.
 *
 * The page loads Leaflet and pulls live marker data from the JSON endpoint
 * below so the map can be refreshed without a full page reload.
 */
class MapController extends Controller
{
    public function __construct(protected IncidentHotspotService $hotspotService) {}

    public function index(): View
    {
        return view('map.index', [
            'canSeeHotspots' => Auth::user()->can(Permission::HotspotsView->value),
            'hall' => [
                'latitude' => (float) config('sagip.hall.latitude'),
                'longitude' => (float) config('sagip.hall.longitude'),
            ],
        ]);
    }

    public function data(): JsonResponse
    {
        $user = Auth::user();

        $requestsQuery = EmergencyRequest::whereNotIn('status', ['resolved', 'cancelled'])
            ->select('id', 'category', 'urgency', 'status', 'latitude', 'longitude', 'source');

        // Residents may see the location/status of only their own active
        // requests. Operational personnel and officials may see the full
        // response map.
        if ($user->isResident()) {
            $requestsQuery->where('resident_id', $user->id);
        }

        $requests = $requestsQuery->get();

        $personnel = collect();

        if ($user->isOfficialOrAdmin() || $user->isPersonnel()) {
            $personnel = ResponsePersonnel::where('is_available', true)
                ->select('id', 'name', 'specialization', 'specializations', 'latitude', 'longitude', 'current_workload')
                ->get()
                ->map(fn (ResponsePersonnel $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'specialization' => $p->specializationLabels(),
                    'latitude' => $p->latitude,
                    'longitude' => $p->longitude,
                    'current_workload' => $p->current_workload,
                ]);
        }

        // Feature 4: every role may see where to evacuate to.
        $centers = EvacuationCenter::whereNot('status', EvacuationCenterStatus::Closed->value)
            ->get()
            ->map(fn (EvacuationCenter $center) => [
                'id' => $center->id,
                'name' => $center->name,
                'latitude' => $center->latitude,
                'longitude' => $center->longitude,
                'status' => $center->status->value,
                'status_label' => $center->status->label(),
                'capacity' => $center->capacity,
                'current_occupancy' => $center->current_occupancy,
                'remaining_capacity' => $center->remainingCapacity(),
                'occupancy_percentage' => $center->occupancyPercentage(),
                'accepting' => $center->canAcceptEvacuees(),
                'contact_number' => $center->contact_number,
            ]);

        // Hotspots aggregate other residents' reports, so they are staff-only.
        $hotspots = $user->can(Permission::HotspotsView->value)
            ? $this->hotspotService->hotspots()
            : collect();

        return response()->json([
            'requests' => $requests,
            'personnel' => $personnel,
            'evacuation_centers' => $centers,
            'hotspots' => $hotspots,
        ]);
    }
}
