<?php

namespace App\Services;

use App\Models\EmergencyRequest;
use Illuminate\Support\Collection;

/**
 * Feature 4: incident hotspots for the map.
 *
 * Incidents are bucketed into a fixed geographic grid (a barangay is small
 * enough that rounded degrees are a fine proxy for distance) and a bucket
 * becomes a hotspot once it holds `min_incidents` reports inside the recency
 * window. Each hotspot carries a 0–1 intensity blending how many incidents it
 * holds with how recent they are, which is what the map renders as radius and
 * colour.
 */
class IncidentHotspotService
{
    /**
     * @return Collection<int, array{
     *     latitude: float,
     *     longitude: float,
     *     incidents: int,
     *     recent_incidents: int,
     *     intensity: float,
     *     dominant_category: ?string,
     *     last_reported_at: ?string
     * }>
     */
    public function hotspots(): Collection
    {
        $precision = (int) config('sagip.hotspots.grid_precision', 3);
        $recencyDays = (int) config('sagip.hotspots.recency_days', 30);
        $minIncidents = max(1, (int) config('sagip.hotspots.min_incidents', 2));

        $since = now()->subDays($recencyDays);
        $recentThreshold = now()->subDays(max(1, (int) ceil($recencyDays / 4)));

        $incidents = EmergencyRequest::query()
            ->where('created_at', '>=', $since)
            ->whereNotIn('status', ['cancelled'])
            ->get(['id', 'category', 'latitude', 'longitude', 'created_at']);

        $buckets = $incidents->groupBy(fn (EmergencyRequest $request) => sprintf(
            '%s|%s',
            number_format((float) $request->latitude, $precision, '.', ''),
            number_format((float) $request->longitude, $precision, '.', ''),
        ));

        $qualifying = $buckets->filter(fn (Collection $bucket) => $bucket->count() >= $minIncidents);

        if ($qualifying->isEmpty()) {
            return collect();
        }

        $busiest = (int) $qualifying->max(fn (Collection $bucket) => $bucket->count());

        return $qualifying
            ->map(function (Collection $bucket) use ($busiest, $recentThreshold) {
                $recent = $bucket->filter(
                    fn (EmergencyRequest $request) => $request->created_at->greaterThanOrEqualTo($recentThreshold)
                )->count();

                $density = $busiest > 0 ? $bucket->count() / $busiest : 0.0;
                $recency = $bucket->count() > 0 ? $recent / $bucket->count() : 0.0;

                return [
                    'latitude' => round((float) $bucket->avg(fn (EmergencyRequest $r) => (float) $r->latitude), 7),
                    'longitude' => round((float) $bucket->avg(fn (EmergencyRequest $r) => (float) $r->longitude), 7),
                    'incidents' => $bucket->count(),
                    'recent_incidents' => $recent,
                    // Weighted toward raw density, nudged up by recency.
                    'intensity' => round(min(1.0, ($density * 0.7) + ($recency * 0.3)), 3),
                    'dominant_category' => $this->dominantCategory($bucket),
                    'last_reported_at' => $bucket->max(fn (EmergencyRequest $r) => $r->created_at)?->toIso8601String(),
                ];
            })
            ->sortByDesc('incidents')
            ->values();
    }

    /**
     * @param  Collection<int, EmergencyRequest>  $bucket
     */
    protected function dominantCategory(Collection $bucket): ?string
    {
        $counts = $bucket
            ->pluck('category')
            ->filter()
            ->countBy();

        if ($counts->isEmpty()) {
            return null;
        }

        return (string) $counts->sortDesc()->keys()->first();
    }
}
