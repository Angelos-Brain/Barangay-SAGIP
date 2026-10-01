<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\EmergencyRequest;
use App\Models\ResponsePersonnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Feature 3: Audit Log.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_model_writes_an_audit_entry_with_the_new_values(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);
        $this->actingAs($official);

        $personnel = ResponsePersonnel::create([
            'name' => 'Medic One',
            'specializations' => ['medical'],
            'is_available' => true,
            'latitude' => 13.5920,
            'longitude' => 124.2050,
        ]);

        $entry = AuditLog::where('action', 'response_personnel.created')
            ->where('auditable_id', $personnel->id)
            ->sole();

        $this->assertSame($official->id, $entry->user_id);
        $this->assertSame(UserRole::Official, $entry->user_role);
        $this->assertNull($entry->before);
        $this->assertSame('Medic One', $entry->after['name']);
    }

    public function test_updating_a_model_records_only_the_attributes_that_moved(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);
        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $request = $this->createRequest($resident);

        $this->actingAs($official);
        $request->update(['status' => RequestStatus::Validated]);

        $entry = AuditLog::where('action', 'emergency_request.updated')->latest('id')->sole();

        $this->assertSame(['status' => 'submitted'], $entry->before);
        $this->assertSame(['status' => 'validated'], $entry->after);
        $this->assertArrayNotHasKey('description', $entry->after);
    }

    public function test_deleting_a_model_records_the_previous_state(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);
        $this->actingAs($official);

        $personnel = ResponsePersonnel::create([
            'name' => 'Temp Responder',
            'specializations' => ['general_assistance'],
            'is_available' => true,
            'latitude' => 13.5920,
            'longitude' => 124.2050,
        ]);
        $personnelId = $personnel->id;
        $personnel->delete();

        $entry = AuditLog::where('action', 'response_personnel.deleted')
            ->where('auditable_id', $personnelId)
            ->sole();

        $this->assertSame('Temp Responder', $entry->before['name']);
        $this->assertNull($entry->after);
    }

    public function test_password_is_never_written_to_the_trail(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $user = User::create([
            'name' => 'New Staff',
            'email' => 'new-staff@sagip.test',
            'password' => 'super-secret-password',
            'role' => UserRole::Tanod,
        ]);

        $entry = AuditLog::where('action', 'user.created')->where('auditable_id', $user->id)->sole();

        $this->assertSame('[redacted]', $entry->after['password']);
        $this->assertStringNotContainsString('super-secret-password', json_encode($entry->after));
    }

    public function test_sign_in_and_sign_out_are_recorded(): void
    {
        $resident = User::factory()->create([
            'role' => UserRole::Resident,
            'email' => 'auditme@sagip.test',
        ]);

        $this->post(route('login'), ['email' => 'auditme@sagip.test', 'password' => 'password']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $resident->id]);

        $this->post(route('logout'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.logout', 'user_id' => $resident->id]);
    }

    public function test_a_rejected_sign_in_is_recorded_without_an_actor(): void
    {
        User::factory()->create(['role' => UserRole::Resident, 'email' => 'auditme@sagip.test']);

        $this->post(route('login'), ['email' => 'auditme@sagip.test', 'password' => 'wrong-password']);

        $entry = AuditLog::where('action', 'auth.failed')->sole();

        $this->assertNull($entry->user_id);
        $this->assertStringContainsString('auditme@sagip.test', $entry->description);
    }

    public function test_entries_cannot_be_modified_or_deleted(): void
    {
        $entry = AuditLog::create(['action' => 'test.action']);

        try {
            $entry->update(['action' => 'tampered']);
            $this->fail('An audit entry was updated.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            $entry->delete();
            $this->fail('An audit entry was deleted.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        $this->assertDatabaseHas('audit_logs', ['id' => $entry->id, 'action' => 'test.action']);
    }

    public function test_high_frequency_gps_pings_are_not_audited(): void
    {
        $user = User::factory()->create(['role' => UserRole::Tanod]);
        $personnel = ResponsePersonnel::create([
            'user_id' => $user->id,
            'name' => 'Tanod One',
            'specializations' => ['peace_order'],
            'is_available' => true,
            'latitude' => 13.5920,
            'longitude' => 124.2050,
        ]);

        $before = AuditLog::where('action', 'response_personnel.updated')->count();

        $this->actingAs($user)->post(route('personnel.updateLocation'), [
            'latitude' => 13.5931,
            'longitude' => 124.2061,
        ]);

        $this->assertSame(13.5931, (float) $personnel->fresh()->latitude);
        $this->assertSame($before, AuditLog::where('action', 'response_personnel.updated')->count());
    }

    private function createRequest(User $resident): EmergencyRequest
    {
        return EmergencyRequest::create([
            'resident_id' => $resident->id,
            'description' => 'Kailangan ko ng tulong.',
            'category' => 'general_assistance',
            'category_confidence' => 0.9,
            'urgency' => 'average',
            'urgency_confidence' => 0.9,
            'latitude' => 13.5925,
            'longitude' => 124.2049,
            'status' => RequestStatus::Submitted,
        ]);
    }
}
