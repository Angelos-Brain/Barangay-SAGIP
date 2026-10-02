<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Enums\VulnerabilityTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 1: Account Verification and Profile Expansion.
 */
class AccountVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_a_new_registration_starts_pending(): void
    {
        $this->post(route('register'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan.delacruz@gmail.com',
            'phone_number' => '09171234567',
            'address' => '225, Provincial Road, Calatagan Tibang, Virac, Catanduanes',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $user = User::where('email', 'juan.delacruz@gmail.com')->sole();

        $this->assertSame(VerificationStatus::Pending, $user->verification_status);
        $this->assertFalse($user->isVerified());
    }

    public function test_a_pending_resident_cannot_reach_the_emergency_report_form(): void
    {
        $resident = User::factory()->pendingVerification()->create(['role' => UserRole::Resident]);

        $this->actingAs($resident)
            ->get(route('requests.create'))
            ->assertRedirect(route('account.verification.pending'));

        $this->actingAs($resident)
            ->post(route('requests.store'), [
                'description' => 'May sunog sa kabilang kalye.',
                'latitude' => 13.5925,
                'longitude' => 124.2049,
            ])
            ->assertRedirect(route('account.verification.pending'));

        $this->assertDatabaseCount('emergency_requests', 0);
    }

    public function test_a_rejected_resident_cannot_reach_the_emergency_report_form(): void
    {
        $resident = User::factory()->rejectedVerification()->create(['role' => UserRole::Resident]);

        $this->actingAs($resident)
            ->get(route('requests.create'))
            ->assertRedirect(route('account.verification.pending'));

        $this->actingAs($resident)
            ->get(route('account.verification.pending'))
            ->assertOk()
            ->assertSee('Your account was not approved');
    }

    public function test_a_pending_resident_may_still_finish_their_profile(): void
    {
        $resident = User::factory()->pendingVerification()->create(['role' => UserRole::Resident]);

        $this->actingAs($resident)->get(route('residents.profile.edit'))->assertOk();
        $this->actingAs($resident)->get(route('notifications.index'))->assertOk();
    }

    public function test_a_verified_resident_reaches_the_emergency_report_form(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);

        $this->actingAs($resident)->get(route('requests.create'))->assertOk();
    }

    public function test_an_official_verifies_a_pending_account_and_unlocks_reporting(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);
        $resident = User::factory()->pendingVerification()->create(['role' => UserRole::Resident]);

        $this->actingAs($official)
            ->patch(route('verifications.update', $resident), [
                'decision' => 'verified',
                'note' => 'ID checked at the hall.',
            ])
            ->assertRedirect();

        $resident->refresh();

        $this->assertSame(VerificationStatus::Verified, $resident->verification_status);
        $this->assertNotNull($resident->verified_at);
        $this->assertSame($official->id, $resident->verified_by);
        $this->assertSame('ID checked at the hall.', $resident->verification_note);

        $this->actingAs($resident)->get(route('requests.create'))->assertOk();
    }

    public function test_verification_decisions_are_written_to_the_audit_trail(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);
        $resident = User::factory()->pendingVerification()->create(['role' => UserRole::Resident]);

        $this->actingAs($official)
            ->patch(route('verifications.update', $resident), ['decision' => 'rejected']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.verification_rejected',
            'user_id' => $official->id,
            'auditable_id' => $resident->id,
        ]);
    }

    public function test_only_roles_with_the_verify_permission_may_decide(): void
    {
        $resident = User::factory()->pendingVerification()->create(['role' => UserRole::Resident]);

        foreach ([UserRole::Resident, UserRole::Tanod, UserRole::Medical] as $role) {
            $actor = User::factory()->create(['role' => $role]);

            $this->actingAs($actor)
                ->patch(route('verifications.update', $resident), ['decision' => 'verified'])
                ->assertForbidden();
        }

        $this->assertSame(VerificationStatus::Pending, $resident->fresh()->verification_status);

        foreach ([UserRole::Official, UserRole::Admin] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('verifications.index'))->assertOk();
        }
    }

    public function test_staff_accounts_are_never_held_behind_verification(): void
    {
        foreach (UserRole::staffRoles() as $role) {
            $staff = User::factory()->pendingVerification()->create(['role' => $role]);

            $this->assertTrue($staff->isVerified(), "Staff role [{$role->value}] was gated.");
        }
    }

    public function test_the_review_queue_lists_pending_accounts_with_their_vulnerability_tags(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);
        $resident = User::factory()->pendingVerification()->create([
            'role' => UserRole::Resident,
            'name' => 'Lola Remedios',
        ]);
        $resident->residentProfile()->create([
            'full_name' => 'Lola Remedios',
            'address' => '9, Rizal Street, Calatagan Tibang, Virac, Catanduanes',
            'household_members_count' => 3,
            'vulnerability_tags' => [VulnerabilityTag::Elderly->value, VulnerabilityTag::Pwd->value],
        ]);

        $this->actingAs($official)
            ->get(route('verifications.index'))
            ->assertOk()
            ->assertSee('Lola Remedios')
            ->assertSee('Elderly')
            ->assertSee('PWD');
    }

    public function test_a_resident_saves_and_edits_their_vulnerability_tags(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);

        $profilePayload = [
            'full_name' => 'Maria Santos',
            'address' => '225, Provincial Road, Calatagan Tibang, Virac, Catanduanes',
            'household_members_count' => 4,
            'birthdate' => '1980-05-12',
            'sex' => 'female',
            'civil_status' => 'married',
            'purok_sitio' => 'Purok 3',
            'emergency_contact_name' => 'Jose Santos',
            'emergency_contact_number' => '09171234567',
        ];

        $this->actingAs($resident)
            ->put(route('residents.profile.update'), $profilePayload + [
                'vulnerability_tags' => ['elderly', 'infant'],
            ])
            ->assertRedirect(route('requests.create'));

        $profile = $resident->fresh()->residentProfile;
        $this->assertSame(['elderly', 'infant'], $profile->vulnerabilityTagValues());
        $this->assertTrue($profile->hasVulnerableMembers());

        // Editing replaces the previous selection rather than appending to it.
        $this->actingAs($resident)
            ->put(route('residents.profile.update'), $profilePayload + ['vulnerability_tags' => ['pregnant']]);

        $this->assertSame(['pregnant'], $resident->fresh()->residentProfile->vulnerabilityTagValues());
    }

    public function test_none_is_mutually_exclusive_with_the_other_markers(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);

        $this->actingAs($resident)
            ->put(route('residents.profile.update'), [
                'full_name' => 'Maria Santos',
                'address' => '225, Provincial Road, Calatagan Tibang, Virac, Catanduanes',
                'household_members_count' => 4,
                'birthdate' => '1980-05-12',
                'sex' => 'female',
                'civil_status' => 'married',
                'purok_sitio' => 'Purok 3',
                'emergency_contact_name' => 'Jose Santos',
                'emergency_contact_number' => '09171234567',
                'vulnerability_tags' => ['elderly', 'none'],
            ]);

        $profile = $resident->fresh()->residentProfile;

        $this->assertSame(['none'], $profile->vulnerabilityTagValues());
        $this->assertFalse($profile->hasVulnerableMembers());
    }

    public function test_unknown_vulnerability_markers_are_rejected(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);

        $this->actingAs($resident)
            ->from(route('residents.profile.edit'))
            ->put(route('residents.profile.update'), [
                'full_name' => 'Maria Santos',
                'address' => '225, Provincial Road, Calatagan Tibang, Virac, Catanduanes',
                'household_members_count' => 4,
                'birthdate' => '1980-05-12',
                'sex' => 'female',
                'civil_status' => 'married',
                'purok_sitio' => 'Purok 3',
                'emergency_contact_name' => 'Jose Santos',
                'emergency_contact_number' => '09171234567',
                'vulnerability_tags' => ['vampire'],
            ])
            ->assertSessionHasErrors('vulnerability_tags.0');
    }

    public function test_profile_cannot_be_saved_with_empty_fields(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);

        $this->actingAs($resident)
            ->from(route('residents.profile.edit'))
            ->put(route('residents.profile.update'), [
                'full_name' => 'Maria Santos',
                'address' => '225, Provincial Road, Calatagan Tibang, Virac, Catanduanes',
                'household_members_count' => 4,
            ])
            ->assertSessionHasErrors([
                'birthdate', 'sex', 'civil_status', 'purok_sitio',
                'vulnerability_tags', 'emergency_contact_name', 'emergency_contact_number',
            ])
            ->assertRedirect(route('residents.profile.edit'));

        $this->assertNull($resident->fresh()->residentProfile);
    }
}
