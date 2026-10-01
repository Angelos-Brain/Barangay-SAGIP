<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\EmergencyRequest;
use App\Models\EvacuationCenter;
use App\Models\ResponsePersonnel;
use App\Models\User;
use App\Support\BackNavigator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 9: universal back navigation.
 */
class BackNavigationTest extends TestCase
{
    use RefreshDatabase;

    private BackNavigator $navigator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->navigator = app(BackNavigator::class);
    }

    public function test_the_home_target_follows_the_role(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $pending = User::factory()->pendingVerification()->create(['role' => UserRole::Resident]);

        $this->assertSame(route('requests.create'), $this->navigator->homeUrlFor($resident));
        $this->assertSame(route('account.verification.pending'), $this->navigator->homeUrlFor($pending));

        foreach (UserRole::staffRoles() as $role) {
            $staff = User::factory()->create(['role' => $role]);
            $this->assertSame(route('dashboard'), $this->navigator->homeUrlFor($staff));
        }
    }

    public function test_it_returns_to_the_page_the_user_came_from(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);

        $resolved = $this->navigator->resolve(
            $official,
            route('personnel.create'),
            route('personnel.index'),
        );

        $this->assertSame(route('personnel.index'), $resolved);
    }

    public function test_it_falls_back_to_home_when_there_is_no_usable_previous_page(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);

        // No referer at all.
        $this->assertSame(
            route('dashboard'),
            $this->navigator->resolve($official, route('personnel.create'), null)
        );

        // An external referer must never be a back target.
        $this->assertSame(
            route('dashboard'),
            $this->navigator->resolve($official, route('personnel.create'), 'https://evil.example.com/phish')
        );

        // The page they are already on is not a destination.
        $this->assertSame(
            route('dashboard'),
            $this->navigator->resolve($official, route('personnel.create'), route('personnel.create'))
        );
    }

    public function test_back_never_points_at_an_auth_route(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);

        foreach ([route('login'), route('register'), route('admin.login'), url('logout')] as $previous) {
            $this->assertSame(
                route('requests.create'),
                $this->navigator->resolve($resident, route('requests.index'), $previous),
                "Back should not return to {$previous}"
            );
        }
    }

    public function test_the_control_is_hidden_on_the_role_home_screen(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $official = User::factory()->create(['role' => UserRole::Official]);

        $this->assertFalse($this->navigator->shouldShow($resident, route('requests.create')));
        $this->assertFalse($this->navigator->shouldShow($official, route('dashboard')));

        $this->assertTrue($this->navigator->shouldShow($resident, route('requests.index')));
        $this->assertTrue($this->navigator->shouldShow($official, route('personnel.index')));
    }

    public function test_every_resident_screen_renders_a_back_control(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $request = $this->incident($resident);

        $screens = [
            route('requests.index'),
            route('requests.show', $request),
            route('residents.profile.edit'),
            route('notifications.index'),
            route('map.index'),
            route('evacuation-centers.index'),
            route('account.photo.edit'),
        ];

        foreach ($screens as $screen) {
            $this->actingAs($resident)
                ->get($screen)
                ->assertOk()
                ->assertSee('data-back-link', escape: false);
        }

        // The resident's home screen is the one place it is deliberately absent.
        $this->actingAs($resident)
            ->get(route('requests.create'))
            ->assertOk()
            ->assertDontSee('data-back-link', escape: false);
    }

    public function test_every_staff_screen_renders_a_back_control(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $request = $this->incident($resident);

        $personnel = ResponsePersonnel::create([
            'user_id' => null,
            'name' => 'Medic One',
            'specializations' => ['medical'],
            'is_available' => true,
            'latitude' => 13.5920,
            'longitude' => 124.2050,
        ]);

        $center = EvacuationCenter::create([
            'name' => 'Virac Central Elementary School',
            'latitude' => 13.5822,
            'longitude' => 124.2311,
            'capacity' => 200,
            'current_occupancy' => 10,
            'status' => 'open',
        ]);

        $screens = [
            route('requests.index'),
            route('requests.show', $request),
            route('requests.assign.edit', $request),
            route('personnel.index'),
            route('personnel.create'),
            route('personnel.edit', $personnel),
            route('evacuation-centers.index'),
            route('evacuation-centers.create'),
            route('evacuation-centers.edit', $center),
            route('verifications.index'),
            route('reports.index'),
            route('audit.index'),
            route('map.index'),
            route('notifications.index'),
            route('account.photo.edit'),
        ];

        foreach ($screens as $screen) {
            $this->actingAs($admin)
                ->get($screen)
                ->assertOk()
                ->assertSee('data-back-link', escape: false);
        }

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-back-link', escape: false);
    }

    public function test_the_responder_specialization_screen_renders_a_back_control(): void
    {
        $user = User::factory()->create(['role' => UserRole::Medical]);
        ResponsePersonnel::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'specializations' => ['medical'],
            'is_available' => true,
            'latitude' => 13.5920,
            'longitude' => 124.2050,
        ]);

        $this->actingAs($user)
            ->get(route('personnel.specializations.edit'))
            ->assertOk()
            ->assertSee('data-back-link', escape: false);
    }

    public function test_a_pending_account_sees_no_back_control_on_its_holding_page(): void
    {
        $pending = User::factory()->pendingVerification()->create(['role' => UserRole::Resident]);

        $this->actingAs($pending)
            ->get(route('account.verification.pending'))
            ->assertOk()
            ->assertDontSee('data-back-link', escape: false);

        // But it does appear once they navigate away from it.
        $this->actingAs($pending)
            ->get(route('residents.profile.edit'))
            ->assertOk()
            ->assertSee('data-back-link', escape: false);
    }

    public function test_the_rendered_href_honours_the_referring_page(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);

        $this->actingAs($official)
            ->from(route('personnel.index'))
            ->get(route('personnel.create'))
            ->assertOk()
            ->assertSee('href="'.route('personnel.index').'"', escape: false);
    }

    public function test_the_rendered_href_falls_back_to_the_role_home(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);

        $this->actingAs($official)
            ->from('https://evil.example.com/phish')
            ->get(route('personnel.create'))
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'"', escape: false)
            ->assertSee('Back to dashboard');
    }

    public function test_a_guest_page_carries_no_back_control(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('data-back-link', escape: false);
        $this->get(route('register'))->assertOk()->assertDontSee('data-back-link', escape: false);
    }

    private function incident(User $resident): EmergencyRequest
    {
        return EmergencyRequest::create([
            'resident_id' => $resident->id,
            'description' => 'Kailangan ko po ng tulong.',
            'category' => 'general_assistance',
            'category_confidence' => 0.9,
            'urgency' => 'average',
            'urgency_confidence' => 0.9,
            'latitude' => 13.5925,
            'longitude' => 124.2049,
            'status' => RequestStatus::Validated,
        ]);
    }
}
