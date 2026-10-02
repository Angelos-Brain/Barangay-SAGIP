<?php

namespace Tests\Feature;

use App\Enums\Specialization;
use App\Enums\UserRole;
use App\Models\ResponsePersonnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 10: Personnel Specialization Tags.
 */
class PersonnelSpecializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_five_specified_specialization_groups_are_selectable(): void
    {
        $values = Specialization::values();

        foreach (['weather', 'peace_order', 'medical', 'general_assistance'] as $expected) {
            $this->assertContains($expected, $values);
        }

        // "Fire/Disaster" is kept as the two granular tags this application has
        // always stored, both of which route to a fire/disaster responder role.
        $this->assertContains('fire', $values);
        $this->assertContains('disaster', $values);
        $this->assertSame(UserRole::FireDisaster, Specialization::Fire->role());
        $this->assertSame(UserRole::FireDisaster, Specialization::Disaster->role());
    }

    public function test_official_can_create_personnel_with_several_tags(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);

        $this->actingAs($official)
            ->post(route('personnel.store'), [
                'name' => 'Multi Responder',
                'email' => 'multi.responder@gmail.com',
                'specializations' => ['medical', 'disaster'],
                'phone_number' => '09170000123',
                'latitude' => 13.5920,
                'longitude' => 124.2050,
            ])
            ->assertRedirect(route('personnel.index'));

        $personnel = ResponsePersonnel::where('name', 'Multi Responder')->sole();

        $this->assertSame(['medical', 'disaster'], $personnel->specializationValues());
        // The legacy single-value column stays populated for the ML payload.
        $this->assertSame('medical', $personnel->specialization);
        $this->assertSame(['medical', 'disaster'], $personnel->incidentCategories());
    }

    public function test_at_least_one_tag_is_required(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);

        $this->actingAs($official)
            ->post(route('personnel.store'), [
                'name' => 'Untagged Responder',
                'specializations' => [],
                'latitude' => 13.5920,
                'longitude' => 124.2050,
            ])
            ->assertSessionHasErrors('specializations');

        $this->assertDatabaseMissing('response_personnel', ['name' => 'Untagged Responder']);
    }

    public function test_unknown_tags_are_rejected(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);

        $this->actingAs($official)
            ->post(route('personnel.store'), [
                'name' => 'Bogus Responder',
                'specializations' => ['zombie_outbreak'],
                'latitude' => 13.5920,
                'longitude' => 124.2050,
            ])
            ->assertSessionHasErrors('specializations.0');
    }

    public function test_responder_can_update_their_own_tags(): void
    {
        $this->withoutVite();

        $user = User::factory()->create(['role' => UserRole::Medical]);
        $personnel = ResponsePersonnel::create([
            'user_id' => $user->id,
            'name' => 'Medic Two',
            'specializations' => ['medical'],
            'is_available' => true,
            'latitude' => 13.5920,
            'longitude' => 124.2050,
        ]);

        $this->actingAs($user)->get(route('personnel.specializations.edit'))->assertOk();

        $this->actingAs($user)
            ->put(route('personnel.specializations.update'), [
                'specializations' => ['medical', 'general_assistance'],
            ])
            ->assertRedirect(route('personnel.specializations.edit'));

        $this->assertSame(
            ['medical', 'general_assistance'],
            $personnel->fresh()->specializationValues()
        );
    }

    public function test_a_resident_cannot_change_responder_tags(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);

        $this->actingAs($resident)
            ->put(route('personnel.specializations.update'), ['specializations' => ['medical']])
            ->assertForbidden();
    }

    public function test_legacy_rows_without_tags_fall_back_to_the_single_column(): void
    {
        $personnel = ResponsePersonnel::create([
            'name' => 'Legacy Responder',
            'specialization' => 'peace_order',
            'is_available' => true,
            'latitude' => 13.5920,
            'longitude' => 124.2050,
        ]);

        $this->assertSame(['peace_order'], $personnel->specializationValues());
        $this->assertTrue($personnel->hasSpecialization(Specialization::PeaceOrder));
    }

    public function test_incident_categories_map_to_the_responding_specializations(): void
    {
        $this->assertSame(
            [Specialization::Medical],
            Specialization::forIncidentCategory('medical')
        );

        $this->assertSame(
            [Specialization::Disaster, Specialization::Weather],
            Specialization::forIncidentCategory('disaster')
        );

        // An unknown label must never silence an alert.
        $this->assertSame(
            [Specialization::GeneralAssistance],
            Specialization::forIncidentCategory('not_a_real_category')
        );
    }
}
