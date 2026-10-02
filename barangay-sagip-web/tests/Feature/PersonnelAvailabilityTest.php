<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ResponsePersonnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonnelAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function createPersonnel(User $user, array $overrides = []): ResponsePersonnel
    {
        return ResponsePersonnel::create(array_merge([
            'user_id' => $user->id,
            'name' => $user->name,
            'specialization' => 'general_assistance',
            'is_available' => true,
            'current_workload' => 0,
            'latitude' => 13.5925,
            'longitude' => 124.2049,
        ], $overrides));
    }

    public function test_personnel_can_mark_themselves_unavailable_with_a_reason(): void
    {
        $user = User::factory()->create(['role' => UserRole::Personnel]);
        $personnel = $this->createPersonnel($user);

        $this->actingAs($user)
            ->post(route('personnel.updateOwnAvailability'), [
                'is_available' => '0',
                'unavailability_reason' => 'On sick leave until Friday.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $personnel->refresh();
        $this->assertFalse($personnel->is_available);
        $this->assertSame('On sick leave until Friday.', $personnel->unavailability_reason);
    }

    public function test_marking_available_again_clears_the_reason(): void
    {
        $user = User::factory()->create(['role' => UserRole::Personnel]);
        $personnel = $this->createPersonnel($user, [
            'is_available' => false,
            'unavailability_reason' => 'Off duty.',
        ]);

        $this->actingAs($user)
            ->post(route('personnel.updateOwnAvailability'), ['is_available' => '1'])
            ->assertRedirect();

        $personnel->refresh();
        $this->assertTrue($personnel->is_available);
        $this->assertNull($personnel->unavailability_reason);
    }

    public function test_personnel_cannot_change_another_responders_availability(): void
    {
        $user = User::factory()->create(['role' => UserRole::Personnel]);
        $own = $this->createPersonnel($user);
        $other = $this->createPersonnel(User::factory()->create(['role' => UserRole::Personnel]));

        $this->actingAs($user)
            ->post(route('personnel.updateOwnAvailability'), [
                'is_available' => '0',
                'unavailability_reason' => 'Off duty.',
                'personnel_id' => $other->id,
            ]);

        $this->assertFalse($own->refresh()->is_available);
        $this->assertTrue($other->refresh()->is_available);
    }

    public function test_a_reason_is_required_to_mark_unavailable(): void
    {
        $user = User::factory()->create(['role' => UserRole::Personnel]);
        $personnel = $this->createPersonnel($user);

        foreach ([[], ['unavailability_reason' => ''], ['unavailability_reason' => '   ']] as $reason) {
            $this->actingAs($user)
                ->post(route('personnel.updateOwnAvailability'), ['is_available' => '0'] + $reason)
                ->assertSessionHasErrors(['unavailability_reason' => 'Enter the reason you are unavailable.']);
        }

        $this->assertTrue($personnel->refresh()->is_available);
    }

    public function test_the_reason_field_is_required_on_the_dashboard(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['role' => UserRole::Personnel]);
        $this->createPersonnel($user);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Reason for being unavailable')
            ->assertDontSee('(optional)')
            ->assertSee('maxlength="500" required', false);
    }

    public function test_reason_longer_than_500_characters_is_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Personnel]);
        $this->createPersonnel($user);

        $this->actingAs($user)
            ->post(route('personnel.updateOwnAvailability'), [
                'is_available' => '0',
                'unavailability_reason' => str_repeat('a', 501),
            ])
            ->assertSessionHasErrors('unavailability_reason');
    }

    public function test_non_personnel_cannot_use_the_availability_toggle(): void
    {
        foreach ([UserRole::Resident, UserRole::Official] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->post(route('personnel.updateOwnAvailability'), ['is_available' => '0'])
                ->assertForbidden();
        }
    }

    public function test_dashboard_shows_toggle_and_officials_see_the_reason(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['role' => UserRole::Personnel]);
        $this->createPersonnel($user, [
            'is_available' => false,
            'unavailability_reason' => 'Attending disaster training.',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mark as Available')
            ->assertSee('Attending disaster training.');

        $this->actingAs(User::factory()->create(['role' => UserRole::Official]))
            ->get(route('personnel.index'))
            ->assertOk()
            ->assertSee('Attending disaster training.');
    }
}
