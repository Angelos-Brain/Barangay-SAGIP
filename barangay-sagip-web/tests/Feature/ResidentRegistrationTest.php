<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_resident_profile_with_a_complete_address(): void
    {
        $this->post(route('register'), [
            'first_name' => 'Juan',
            'middle_name' => 'Padin',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@gmail.com',
            'phone_number' => '09171234567',
            'address' => '225, Provincial Road, Calatagan Tibang, Virac, Catanduanes',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', [
            'email' => 'juan@gmail.com',
            'role' => UserRole::Resident->value,
        ]);

        $this->assertDatabaseHas('resident_profiles', [
            'full_name' => 'Juan Padin Dela Cruz',
            'address' => '225, Provincial Road, Calatagan Tibang, Virac, Catanduanes',
        ]);
    }

    public function test_registration_rejects_an_incomplete_address(): void
    {
        $this->from(route('register'))
            ->post(route('register'), [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'email' => 'juan@example.com',
                'phone_number' => '09171234567',
                'address' => '225, Provincial Road, Calatagan Tibang',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertSessionHasErrors('address')
            ->assertRedirect(route('register'));
    }

    public function test_registration_rejects_an_address_without_a_house_or_unit_number(): void
    {
        $this->from(route('register'))
            ->post(route('register'), [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'email' => 'juan@example.com',
                'phone_number' => '09171234567',
                'address' => 'Provincial Road, Calatagan Tibang, Virac, Catanduanes',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertSessionHasErrors('address')
            ->assertRedirect(route('register'));
    }

    public function test_profile_update_also_rejects_an_incomplete_address(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);

        $this->actingAs($resident)
            ->from(route('residents.profile.edit'))
            ->put(route('residents.profile.update'), [
                'full_name' => 'Juan Dela Cruz',
                'address' => '225, Provincial Road, Calatagan Tibang',
                'household_members_count' => 1,
            ])
            ->assertSessionHasErrors('address')
            ->assertRedirect(route('residents.profile.edit'));
    }
}
