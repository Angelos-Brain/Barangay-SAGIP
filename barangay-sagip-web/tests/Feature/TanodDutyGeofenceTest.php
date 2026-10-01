<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ResponsePersonnel;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 7: Tanod location lock.
 */
class TanodDutyGeofenceTest extends TestCase
{
    use RefreshDatabase;

    private float $hallLatitude;

    private float $hallLongitude;

    private int $radius;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->hallLatitude = (float) config('sagip.hall.latitude');
        $this->hallLongitude = (float) config('sagip.hall.longitude');
        $this->radius = (int) config('sagip.tanod.check_in_radius_meters');
    }

    /**
     * A point roughly `$metres` north of the barangay hall.
     *
     * @return array{latitude: float, longitude: float}
     */
    private function metresNorthOfHall(float $metres): array
    {
        // 1 degree of latitude is ~111,320 m; north-only movement keeps the
        // longitude term out of the calculation.
        return [
            'latitude' => $this->hallLatitude + ($metres / 111_320),
            'longitude' => $this->hallLongitude,
        ];
    }

    private function tanod(array $attributes = []): array
    {
        $user = User::factory()->create(['role' => UserRole::Tanod]);

        $personnel = ResponsePersonnel::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Tanod '.$user->name,
            'specializations' => ['peace_order'],
            'is_available' => false,
            'latitude' => $this->hallLatitude,
            'longitude' => $this->hallLongitude,
        ], $attributes));

        return [$user, $personnel];
    }

    public function test_the_distance_helper_agrees_with_the_generated_offsets(): void
    {
        $point = $this->metresNorthOfHall(100);

        $distance = Geo::distanceInMeters(
            $this->hallLatitude,
            $this->hallLongitude,
            $point['latitude'],
            $point['longitude'],
        );

        $this->assertEqualsWithDelta(100.0, $distance, 1.0);
    }

    public function test_a_tanod_inside_the_radius_goes_on_duty(): void
    {
        [$user, $personnel] = $this->tanod();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('tanod.checkIn'), $this->metresNorthOfHall($this->radius - 50))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();

        $personnel->refresh();

        $this->assertTrue($personnel->is_available);
        $this->assertNotNull($personnel->on_duty_at);
        $this->assertNotNull($personnel->last_check_in_distance_meters);
        $this->assertLessThanOrEqual($this->radius, (float) $personnel->last_check_in_distance_meters);
        $this->assertNotNull($personnel->last_check_in_latitude);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tanod.checked_in',
            'user_id' => $user->id,
        ]);
    }

    public function test_a_tanod_outside_the_radius_is_rejected_and_flagged(): void
    {
        [$user, $personnel] = $this->tanod();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('tanod.checkIn'), $this->metresNorthOfHall($this->radius + 500))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('latitude');

        $personnel->refresh();

        $this->assertFalse($personnel->is_available);
        $this->assertNull($personnel->on_duty_at);

        // Rejected, and recorded so a pattern of remote attempts is visible.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tanod.check_in_rejected',
            'user_id' => $user->id,
        ]);
    }

    public function test_the_rejection_names_the_distance_and_the_limit(): void
    {
        [$user] = $this->tanod();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('tanod.checkIn'), $this->metresNorthOfHall(1000))
            ->assertInvalid(['latitude' => 'from the barangay hall'])
            ->assertInvalid(['latitude' => 'Check in within '.number_format($this->radius).'m']);
    }

    public function test_a_check_in_just_outside_the_boundary_is_refused(): void
    {
        [$user, $personnel] = $this->tanod();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('tanod.checkIn'), $this->metresNorthOfHall($this->radius + 25))
            ->assertSessionHasErrors('latitude');

        $this->assertFalse($personnel->fresh()->is_available);
    }

    public function test_a_tanod_cannot_go_active_through_the_plain_availability_toggle(): void
    {
        [$user, $personnel] = $this->tanod();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('personnel.updateOwnAvailability'), ['is_available' => 1])
            ->assertSessionHasErrors('is_available');

        $this->assertFalse($personnel->fresh()->is_available);
    }

    public function test_an_official_cannot_mark_an_unchecked_in_tanod_active(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);
        [, $personnel] = $this->tanod();

        $this->actingAs($official)
            ->from(route('personnel.index'))
            ->post(route('personnel.toggleAvailability', $personnel))
            ->assertSessionHasErrors('is_available');

        $this->assertFalse($personnel->fresh()->is_available);

        $this->actingAs($official)
            ->from(route('personnel.edit', $personnel))
            ->put(route('personnel.update', $personnel), [
                'name' => $personnel->name,
                'specializations' => ['peace_order'],
                'latitude' => $this->hallLatitude,
                'longitude' => $this->hallLongitude,
                'is_available' => 1,
            ])
            ->assertSessionHasErrors('is_available');

        $this->assertFalse($personnel->fresh()->is_available);
    }

    public function test_an_official_may_mark_a_checked_in_tanod_active(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);
        [$user, $personnel] = $this->tanod();

        $this->actingAs($user)->post(route('tanod.checkIn'), $this->metresNorthOfHall(10));
        $personnel->refresh()->update(['is_available' => false]);

        $this->actingAs($official)
            ->from(route('personnel.index'))
            ->post(route('personnel.toggleAvailability', $personnel))
            ->assertSessionHasNoErrors();

        $this->assertTrue($personnel->fresh()->is_available);
    }

    public function test_checking_out_clears_the_duty_record(): void
    {
        [$user, $personnel] = $this->tanod();

        $this->actingAs($user)->post(route('tanod.checkIn'), $this->metresNorthOfHall(10));
        $this->assertNotNull($personnel->fresh()->on_duty_at);

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('tanod.checkOut'), ['reason' => 'End of shift'])
            ->assertSessionHasNoErrors();

        $personnel->refresh();

        $this->assertFalse($personnel->is_available);
        $this->assertNull($personnel->on_duty_at);
        $this->assertSame('End of shift', $personnel->unavailability_reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tanod.checked_out']);
    }

    public function test_a_tanod_must_check_in_again_after_checking_out(): void
    {
        [$user, $personnel] = $this->tanod();

        $this->actingAs($user)->post(route('tanod.checkIn'), $this->metresNorthOfHall(10));
        $this->actingAs($user)->post(route('tanod.checkOut'));

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('personnel.updateOwnAvailability'), ['is_available' => 1])
            ->assertSessionHasErrors('is_available');

        $this->assertFalse($personnel->fresh()->is_available);
    }

    public function test_other_responder_roles_are_not_geofenced(): void
    {
        foreach ([UserRole::Medical, UserRole::FireDisaster, UserRole::Weather, UserRole::GeneralAssistant, UserRole::Personnel] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $personnel = ResponsePersonnel::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'specializations' => ['medical'],
                'is_available' => false,
                'latitude' => 13.9,
                'longitude' => 124.9,
            ]);

            $this->actingAs($user)
                ->from(route('dashboard'))
                ->post(route('personnel.updateOwnAvailability'), ['is_available' => 1])
                ->assertSessionHasNoErrors();

            $this->assertTrue(
                $personnel->fresh()->is_available,
                "Role [{$role->value}] should not be geofenced."
            );
        }
    }

    public function test_only_a_tanod_can_reach_the_check_in_endpoint(): void
    {
        foreach ([UserRole::Resident, UserRole::Medical, UserRole::Official, UserRole::Admin] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->post(route('tanod.checkIn'), $this->metresNorthOfHall(10))
                ->assertForbidden();
        }
    }

    public function test_a_check_in_without_coordinates_is_rejected(): void
    {
        [$user] = $this->tanod();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('tanod.checkIn'), [])
            ->assertSessionHasErrors(['latitude', 'longitude']);
    }

    public function test_the_radius_is_configurable(): void
    {
        [$user, $personnel] = $this->tanod();

        config()->set('sagip.tanod.check_in_radius_meters', 2000);

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('tanod.checkIn'), $this->metresNorthOfHall(1500))
            ->assertSessionHasNoErrors();

        $this->assertTrue($personnel->fresh()->is_available);
    }
}
