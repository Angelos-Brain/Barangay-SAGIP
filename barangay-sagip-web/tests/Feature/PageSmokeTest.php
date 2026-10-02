<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\EmergencyRequest;
use App\Models\ResponseAssignment;
use App\Models\ResponsePersonnel;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders every page each role can reach against the seeded demo data, so a
 * broken view, query, or seeder is caught before anyone opens the browser.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    private EmergencyRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);

        $resident = User::where('email', 'resident@sagip.test')->firstOrFail();
        $personnel = ResponsePersonnel::whereHas('user', fn ($q) => $q->where('email', 'personnel@sagip.test'))->firstOrFail();

        $this->request = EmergencyRequest::create([
            'resident_id' => $resident->id,
            'description' => 'May sunog sa kapitbahay namin.',
            'category' => 'fire',
            'category_confidence' => 0.9,
            'urgency' => 'critical',
            'urgency_confidence' => 0.9,
            'latitude' => 13.5925,
            'longitude' => 124.2049,
            'status' => RequestStatus::Assigned,
        ]);

        ResponseAssignment::create([
            'emergency_request_id' => $this->request->id,
            'response_personnel_id' => $personnel->id,
            'assigned_at' => now()->subMinutes(30),
            'completed_at' => now(),
        ]);

        ResponseAssignment::create([
            'emergency_request_id' => $this->request->id,
            'response_personnel_id' => $personnel->id,
            'assigned_at' => now()->subMinutes(5),
        ]);
    }

    public function test_guest_pages_render(): void
    {
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
        $this->get(route('admin.login'))->assertOk();
    }

    public function test_seeded_demo_accounts_can_log_in(): void
    {
        $this->post(route('login'), ['email' => 'resident@sagip.test', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->post(route('logout'));

        $this->post(route('admin.login.store'), ['email' => 'official@sagip.test', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->post(route('admin.logout'));

        $this->post(route('personnel.login.store'), ['email' => 'personnel@sagip.test', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->post(route('admin.logout'));
    }

    public function test_resident_pages_render(): void
    {
        $this->actingAs(User::where('email', 'resident@sagip.test')->firstOrFail());

        $this->get(route('dashboard'))->assertRedirect(route('requests.create'));
        $this->get(route('requests.create'))->assertOk();
        $this->get(route('requests.index'))->assertOk();
        $this->get(route('requests.show', $this->request))->assertOk();
        $this->get(route('residents.profile.edit'))->assertOk();
        $this->get(route('notifications.index'))->assertOk();
        $this->get(route('map.index'))->assertOk();
        $this->getJson(route('map.data'))->assertOk()->assertJsonCount(1, 'requests');
    }

    public function test_personnel_pages_render(): void
    {
        $this->actingAs(User::where('email', 'personnel@sagip.test')->firstOrFail());

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('requests.index'))->assertOk();
        $this->get(route('requests.show', $this->request))->assertOk();
        $this->get(route('notifications.index'))->assertOk();
        $this->get(route('map.index'))->assertOk();
        $this->getJson(route('map.data'))->assertOk();
    }

    public function test_official_pages_render(): void
    {
        $this->actingAs(User::where('email', 'official@sagip.test')->firstOrFail());

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('requests.index'))->assertOk();
        $this->get(route('requests.show', $this->request))->assertOk();
        $this->get(route('requests.assign.edit', $this->request))->assertOk();
        $this->get(route('personnel.index'))->assertOk();
        $this->get(route('personnel.create'))->assertOk();
        $this->get(route('personnel.edit', ResponsePersonnel::first()))->assertOk();
        $this->get(route('reports.index'))->assertOk()->assertSee('30 min');
        $this->get(route('reports.export'))->assertOk();
        $this->get(route('notifications.index'))->assertOk();
        $this->get(route('map.index'))->assertOk();
        $this->getJson(route('map.data'))->assertOk();
    }
}
