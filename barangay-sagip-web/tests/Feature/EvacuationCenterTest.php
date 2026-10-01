<?php

namespace Tests\Feature;

use App\Enums\EvacuationCenterStatus;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\EmergencyRequest;
use App\Models\EvacuationCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 4: Evacuation Center Management and incident hotspots.
 */
class EvacuationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Virac Central Elementary School',
            'address' => 'Rizal Street, Virac, Catanduanes',
            'latitude' => 13.5822,
            'longitude' => 124.2311,
            'capacity' => 200,
            'current_occupancy' => 40,
            'status' => 'open',
            'contact_person' => 'Principal Reyes',
            'contact_number' => '09170001234',
        ], $overrides);
    }

    private function center(array $overrides = []): EvacuationCenter
    {
        return EvacuationCenter::create($this->payload($overrides));
    }

    public function test_an_official_creates_a_center(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);

        $this->actingAs($official)
            ->post(route('evacuation-centers.store'), $this->payload())
            ->assertRedirect(route('evacuation-centers.index'));

        $center = EvacuationCenter::sole();

        $this->assertSame('Virac Central Elementary School', $center->name);
        $this->assertSame(200, $center->capacity);
        $this->assertSame(40, $center->current_occupancy);
        $this->assertSame(EvacuationCenterStatus::Open, $center->status);
        $this->assertSame(160, $center->remainingCapacity());
        $this->assertSame(20, $center->occupancyPercentage());
        $this->assertTrue($center->canAcceptEvacuees());
    }

    public function test_an_admin_updates_occupancy_and_status(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $center = $this->center();

        $this->actingAs($admin)
            ->put(route('evacuation-centers.update', $center), $this->payload([
                'current_occupancy' => 200,
                'status' => 'full',
            ]))
            ->assertRedirect(route('evacuation-centers.index'));

        $center->refresh();

        $this->assertSame(200, $center->current_occupancy);
        $this->assertSame(EvacuationCenterStatus::Full, $center->status);
        $this->assertSame(0, $center->remainingCapacity());
        $this->assertFalse($center->canAcceptEvacuees());
    }

    public function test_occupancy_cannot_exceed_capacity(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);

        $this->actingAs($official)
            ->from(route('evacuation-centers.create'))
            ->post(route('evacuation-centers.store'), $this->payload([
                'capacity' => 50,
                'current_occupancy' => 51,
            ]))
            ->assertSessionHasErrors('current_occupancy');

        $this->assertDatabaseCount('evacuation_centers', 0);
    }

    public function test_a_center_with_evacuees_cannot_be_removed(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);
        $center = $this->center(['current_occupancy' => 12]);

        $this->actingAs($official)
            ->delete(route('evacuation-centers.destroy', $center))
            ->assertRedirect();

        $this->assertDatabaseHas('evacuation_centers', ['id' => $center->id]);

        $center->update(['current_occupancy' => 0]);

        $this->actingAs($official)
            ->delete(route('evacuation-centers.destroy', $center))
            ->assertRedirect(route('evacuation-centers.index'));

        $this->assertDatabaseMissing('evacuation_centers', ['id' => $center->id]);
    }

    public function test_every_role_can_read_the_roster_but_only_managers_can_change_it(): void
    {
        $center = $this->center();

        foreach ([UserRole::Resident, UserRole::Tanod, UserRole::Medical, UserRole::Official, UserRole::Admin] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('evacuation-centers.index'))->assertOk();
        }

        foreach ([UserRole::Resident, UserRole::Tanod, UserRole::Medical] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('evacuation-centers.create'))->assertForbidden();
            $this->actingAs($user)->post(route('evacuation-centers.store'), $this->payload(['name' => 'Sneaky']))->assertForbidden();
            $this->actingAs($user)->put(route('evacuation-centers.update', $center), $this->payload())->assertForbidden();
            $this->actingAs($user)->delete(route('evacuation-centers.destroy', $center))->assertForbidden();
        }

        $this->assertDatabaseMissing('evacuation_centers', ['name' => 'Sneaky']);
    }

    public function test_the_roster_shows_occupancy_and_remaining_space(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $this->center(['name' => 'San Isidro Gymnasium', 'capacity' => 300, 'current_occupancy' => 75]);

        $this->actingAs($resident)
            ->get(route('evacuation-centers.index'))
            ->assertOk()
            ->assertSee('San Isidro Gymnasium')
            ->assertSee('75')
            ->assertSee('300')
            ->assertSee('225'); // remaining capacity
    }

    public function test_the_map_feed_carries_open_centers_and_omits_closed_ones(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);
        $this->center(['name' => 'Open Center', 'status' => 'open']);
        $this->center(['name' => 'Closed Center', 'status' => 'closed', 'current_occupancy' => 0]);

        $response = $this->actingAs($official)->getJson(route('map.data'))->assertOk();

        $names = collect($response->json('evacuation_centers'))->pluck('name');

        $this->assertTrue($names->contains('Open Center'));
        $this->assertFalse($names->contains('Closed Center'));
    }

    public function test_residents_also_receive_evacuation_centers_on_the_map_feed(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $this->center(['name' => 'Open Center']);

        $this->actingAs($resident)
            ->getJson(route('map.data'))
            ->assertOk()
            ->assertJsonCount(1, 'evacuation_centers');
    }

    public function test_hotspots_cluster_incidents_by_density_and_are_staff_only(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $official = User::factory()->create(['role' => UserRole::Official]);

        // Four flood reports within a few metres of each other.
        foreach ([0.00001, 0.00002, 0.00003, 0.00004] as $jitter) {
            $this->incident($resident, 'disaster', 13.5925 + $jitter, 124.2049 + $jitter);
        }

        // A lone report elsewhere must not become a hotspot on its own.
        $this->incident($resident, 'fire', 13.6300, 124.2600);

        $hotspots = collect(
            $this->actingAs($official)->getJson(route('map.data'))->assertOk()->json('hotspots')
        );

        $this->assertCount(1, $hotspots);
        $this->assertSame(4, $hotspots->first()['incidents']);
        $this->assertSame('disaster', $hotspots->first()['dominant_category']);
        $this->assertGreaterThan(0, $hotspots->first()['intensity']);

        // Residents never see other households' aggregated reports.
        $this->actingAs($resident)
            ->getJson(route('map.data'))
            ->assertOk()
            ->assertJsonCount(0, 'hotspots');
    }

    public function test_incidents_outside_the_recency_window_do_not_form_a_hotspot(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $official = User::factory()->create(['role' => UserRole::Official]);

        $recencyDays = (int) config('sagip.hotspots.recency_days');

        foreach ([0.00001, 0.00002, 0.00003] as $jitter) {
            $this->incident($resident, 'disaster', 13.5925 + $jitter, 124.2049 + $jitter)
                ->forceFill(['created_at' => now()->subDays($recencyDays + 5)])
                ->save();
        }

        $this->actingAs($official)
            ->getJson(route('map.data'))
            ->assertOk()
            ->assertJsonCount(0, 'hotspots');
    }

    public function test_center_changes_are_written_to_the_audit_trail(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);

        $this->actingAs($official)->post(route('evacuation-centers.store'), $this->payload());
        $center = EvacuationCenter::sole();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'evacuation_center.created',
            'auditable_id' => $center->id,
            'user_id' => $official->id,
        ]);

        $this->actingAs($official)->put(
            route('evacuation-centers.update', $center),
            $this->payload(['current_occupancy' => 90])
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'evacuation_center.updated',
            'auditable_id' => $center->id,
        ]);
    }

    private function incident(User $resident, string $category, float $latitude, float $longitude): EmergencyRequest
    {
        return EmergencyRequest::create([
            'resident_id' => $resident->id,
            'description' => 'Bumabaha na sa amin.',
            'category' => $category,
            'category_confidence' => 0.9,
            'urgency' => 'high',
            'urgency_confidence' => 0.9,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'status' => RequestStatus::Validated,
        ]);
    }
}
