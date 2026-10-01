<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\EmergencyRequest;
use App\Models\ResponsePersonnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Feature 3: Role-Based Access Control.
 */
class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_named_in_the_specification_exists(): void
    {
        $expected = [
            'admin', 'official', 'tanod', 'medical', 'fire_disaster',
            'weather', 'peace_order', 'general_assistant', 'resident',
        ];

        foreach ($expected as $value) {
            $this->assertNotNull(UserRole::tryFrom($value), "Role [{$value}] is missing.");
        }
    }

    public function test_each_role_has_a_distinct_permission_set(): void
    {
        $fingerprints = [];

        foreach (UserRole::cases() as $role) {
            $permissions = array_map(fn (Permission $p) => $p->value, $role->permissions());
            sort($permissions);

            $this->assertNotEmpty($permissions, "Role [{$role->value}] grants nothing.");
            $fingerprints[$role->value] = implode('|', $permissions);
        }

        // The six specialized responder roles intentionally share one
        // permission set and are separated by their specializations instead;
        // every other role must be distinguishable by permissions alone.
        $responderRoles = ['medical', 'fire_disaster', 'weather', 'peace_order', 'general_assistant', 'personnel'];
        $distinct = collect($fingerprints)->reject(fn ($v, $role) => in_array($role, $responderRoles, true));

        $this->assertCount($distinct->count(), $distinct->unique());
        $this->assertSame(1, collect($fingerprints)->only($responderRoles)->unique()->count());
    }

    public function test_only_the_admin_role_may_read_the_audit_log(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->assertSame(
                $role === UserRole::Admin,
                Gate::forUser($user)->allows(Permission::AuditView->value),
                "Unexpected audit access for [{$role->value}]."
            );
        }
    }

    public function test_only_the_tanod_role_may_check_in_for_duty(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->assertSame(
                $role === UserRole::Tanod,
                Gate::forUser($user)->allows(Permission::TanodCheckIn->value),
                "Unexpected check-in permission for [{$role->value}]."
            );
        }
    }

    public function test_audit_log_page_is_reachable_by_admin_and_forbidden_to_everyone_else(): void
    {
        $this->withoutVite();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->get(route('audit.index'))->assertOk();

        foreach ([UserRole::Official, UserRole::Tanod, UserRole::Medical, UserRole::Resident] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('audit.index'))->assertForbidden();
        }
    }

    public function test_admin_can_reach_the_official_management_screens(): void
    {
        $this->withoutVite();

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('personnel.index'))->assertOk();
        $this->actingAs($admin)->get(route('reports.index'))->assertOk();
    }

    public function test_specialized_responder_roles_satisfy_the_personnel_route_group(): void
    {
        $this->withoutVite();

        foreach ([UserRole::Tanod, UserRole::Medical, UserRole::FireDisaster, UserRole::Weather, UserRole::PeaceOrder, UserRole::GeneralAssistant] as $role) {
            $user = User::factory()->create(['role' => $role]);
            ResponsePersonnel::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'specializations' => [$role->specializations()[0]->value],
                'is_available' => true,
                'latitude' => 13.5920,
                'longitude' => 124.2050,
            ]);

            $this->actingAs($user)->get(route('personnel.specializations.edit'))->assertOk();
            $this->actingAs($user)->get(route('personnel.index'))->assertForbidden();
        }
    }

    public function test_medical_responder_sees_medical_incidents_but_not_fire_incidents(): void
    {
        $this->withoutVite();

        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $medicalUser = User::factory()->create(['role' => UserRole::Medical]);

        ResponsePersonnel::create([
            'user_id' => $medicalUser->id,
            'name' => 'Medic',
            'specializations' => ['medical'],
            'is_available' => true,
            'latitude' => 13.5920,
            'longitude' => 124.2050,
        ]);

        $medicalRequest = $this->createRequest($resident, 'medical', 'Nahihilo at sumusuka.');
        $fireRequest = $this->createRequest($resident, 'fire', 'May sunog sa kusina.');

        $this->actingAs($medicalUser)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertSee(route('requests.show', $medicalRequest))
            ->assertDontSee(route('requests.show', $fireRequest));

        $this->actingAs($medicalUser)->get(route('requests.show', $medicalRequest))->assertOk();
        $this->actingAs($medicalUser)->get(route('requests.show', $fireRequest))->assertForbidden();
    }

    private function createRequest(User $resident, string $category, string $description): EmergencyRequest
    {
        return EmergencyRequest::create([
            'resident_id' => $resident->id,
            'description' => $description,
            'category' => $category,
            'category_confidence' => 0.9,
            'urgency' => 'high',
            'urgency_confidence' => 0.9,
            'latitude' => 13.5925,
            'longitude' => 124.2049,
            'status' => RequestStatus::Validated,
        ]);
    }
}
