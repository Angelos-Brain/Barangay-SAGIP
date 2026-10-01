<?php

namespace Tests\Feature;

use App\Enums\IncidentOutcome;
use App\Enums\RequestStatus;
use App\Enums\SosReason;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\EmergencyRequest;
use App\Models\ResponseAssignment;
use App\Models\ResponsePersonnel;
use App\Models\User;
use App\Notifications\SosTriggered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature 2: SOS reason, anti-false-alarm friction, corroborating data,
 * post-incident validation, and the false-alarm trust tier.
 */
class SosValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'latitude' => 13.5925,
            'longitude' => 124.2049,
            'accuracy' => 12.5,
            'triggered_at' => now()->toIso8601String(),
            'reason' => 'medical',
            'device_id' => 'device-abc123',
        ], $overrides);
    }

    private function resident(): User
    {
        return User::factory()->create(['role' => UserRole::Resident]);
    }

    /**
     * @param  list<string>  $specializations
     */
    private function responder(UserRole $role, array $specializations): User
    {
        $user = User::factory()->create(['role' => $role]);

        ResponsePersonnel::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'specializations' => $specializations,
            'is_available' => true,
            'latitude' => 13.5920,
            'longitude' => 124.2050,
        ]);

        return $user;
    }

    private function sos(User $resident, array $overrides = []): EmergencyRequest
    {
        $this->actingAs($resident)->postJson(route('sos.store'), $this->payload($overrides))->assertCreated();

        return EmergencyRequest::latest('id')->firstOrFail();
    }

    private function assign(EmergencyRequest $request, User $responder, bool $completed = false): void
    {
        ResponseAssignment::create([
            'emergency_request_id' => $request->id,
            'response_personnel_id' => $responder->responsePersonnel->id,
            'assigned_at' => now(),
            'completed_at' => $completed ? now() : null,
        ]);
    }

    private function closedSosFor(User $resident, RequestStatus $status = RequestStatus::Resolved): EmergencyRequest
    {
        $request = $this->sos($resident);
        $request->update(['status' => $status]);
        $this->travel(121)->seconds();

        return $request;
    }

    // ---- 1. Mandatory reason -------------------------------------------------

    public function test_an_sos_without_a_reason_is_rejected_and_nothing_is_filed(): void
    {
        $this->actingAs($this->resident())
            ->postJson(route('sos.store'), $this->payload(['reason' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->actingAs($this->resident())
            ->postJson(route('sos.smsFallback'), $this->payload(['reason' => 'prank']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->assertDatabaseCount('emergency_requests', 0);
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function blankOtherTexts(): array
    {
        return [
            'missing' => [null],
            'empty' => [''],
            'whitespace only' => ['    '],
        ];
    }

    #[DataProvider('blankOtherTexts')]
    public function test_other_requires_a_description(?string $text): void
    {
        $this->actingAs($this->resident())
            ->postJson(route('sos.store'), $this->payload(['reason' => 'other', 'reason_other' => $text]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason_other']);

        $this->assertDatabaseCount('emergency_requests', 0);
    }

    public function test_other_description_is_limited_to_100_characters(): void
    {
        $this->actingAs($this->resident())
            ->postJson(route('sos.store'), $this->payload(['reason' => 'other', 'reason_other' => str_repeat('a', 101)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason_other']);

        $this->actingAs($this->resident())
            ->postJson(route('sos.store'), $this->payload(['reason' => 'other', 'reason_other' => str_repeat('a', 100)]))
            ->assertCreated();
    }

    public function test_the_reason_and_other_text_are_stored_on_the_incident(): void
    {
        Notification::fake();

        $request = $this->sos($this->resident(), ['reason' => 'other', 'reason_other' => 'Nahulog sa balon']);

        $this->assertSame(SosReason::Other, $request->sos_reason);
        $this->assertSame('Nahulog sa balon', $request->sos_reason_other);
        $this->assertNull($request->category);
        $this->assertStringContainsString('Nahulog sa balon', $request->description);
    }

    public function test_free_text_is_discarded_unless_the_reason_is_other(): void
    {
        Notification::fake();

        $request = $this->sos($this->resident(), ['reason' => 'fire', 'reason_other' => 'ignored']);

        $this->assertSame(SosReason::Fire, $request->sos_reason);
        $this->assertNull($request->sos_reason_other);
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function reasonCategories(): array
    {
        return [
            'medical' => ['medical', 'medical'],
            'fire' => ['fire', 'fire'],
            'crime' => ['crime_peace_order', 'peace_order'],
            'weather' => ['weather_flood', 'disaster'],
            'missing person' => ['missing_person', 'peace_order'],
            'accident' => ['accident', 'medical'],
        ];
    }

    #[DataProvider('reasonCategories')]
    public function test_the_reason_sets_the_routing_category(string $reason, ?string $category): void
    {
        Notification::fake();

        $request = $this->sos($this->resident(), ['reason' => $reason]);

        $this->assertSame($category, $request->category);
    }

    public function test_the_reason_routes_the_alert_to_matching_specializations_only(): void
    {
        Notification::fake();

        $firefighter = $this->responder(UserRole::FireDisaster, ['fire']);
        $medic = $this->responder(UserRole::Medical, ['medical']);
        $official = User::factory()->create(['role' => UserRole::Official]);

        $this->sos($this->resident(), ['reason' => 'fire']);

        Notification::assertSentTo([$firefighter, $official], SosTriggered::class);
        Notification::assertNotSentTo($medic, SosTriggered::class);
    }

    public function test_weather_reason_alerts_weather_and_disaster_responders(): void
    {
        Notification::fake();

        $weather = $this->responder(UserRole::Weather, ['weather']);
        $disaster = $this->responder(UserRole::FireDisaster, ['disaster']);
        $police = $this->responder(UserRole::PeaceOrder, ['peace_order']);

        $this->sos($this->resident(), ['reason' => 'weather_flood']);

        Notification::assertSentTo([$weather, $disaster], SosTriggered::class);
        Notification::assertNotSentTo($police, SosTriggered::class);
    }

    // ---- 2. Hold-to-confirm + cancel window ----------------------------------

    public function test_the_sos_control_renders_the_hold_reason_and_cancel_steps(): void
    {
        $this->withoutVite();

        $this->actingAs($this->resident())
            ->get(route('requests.create'))
            ->assertOk()
            ->assertSee('Press and hold to start an SOS.')
            ->assertSee('What is the emergency?')
            ->assertSee('Confirm SOS:')
            ->assertSee('holdMs: 2000', false)
            ->assertSee('cancelWindowSeconds: 5', false)
            ->assertSee('Missing Person');
    }

    // ---- 3. Corroborating data ----------------------------------------------

    public function test_the_incident_carries_gps_accuracy_device_verification_and_timestamp(): void
    {
        Notification::fake();
        $this->freezeSecond();

        $request = $this->sos($this->resident());

        $this->assertSame('12.50', (string) $request->location_accuracy_meters);
        $this->assertSame('device-abc123', $request->device_id);
        $this->assertSame(VerificationStatus::Verified, $request->reporter_verification_status);
        $this->assertTrue($request->created_at->equalTo(now()));
        $this->assertSame([], $request->location_flags);

        $audit = AuditLog::where('action', 'sos.triggered')->sole();
        $this->assertSame('medical', $audit->after['reason']);
        $this->assertSame('12.50', $audit->after['accuracy_meters']);
        $this->assertSame('device-abc123', $audit->after['device_id']);
        $this->assertSame('verified', $audit->after['reporter_verification_status']);
        $this->assertSame(0, $audit->after['reporter_false_alarm_count']);
    }

    public function test_poor_accuracy_and_out_of_bounds_are_flagged_but_still_dispatched(): void
    {
        Notification::fake();
        $official = User::factory()->create(['role' => UserRole::Official]);

        // ~11 km north of the hall, with a 450 m accuracy circle.
        $request = $this->sos($this->resident(), ['latitude' => 13.6920, 'accuracy' => 450]);

        $this->assertSame(
            [EmergencyRequest::FLAG_POOR_ACCURACY, EmergencyRequest::FLAG_OUTSIDE_BOUNDS],
            $request->location_flags,
        );
        $this->assertStringContainsString('outside the barangay', $request->review_reason);
        Notification::assertSentTo($official, SosTriggered::class);
    }

    public function test_a_missing_accuracy_value_is_flagged(): void
    {
        Notification::fake();

        $request = $this->sos($this->resident(), ['accuracy' => null]);

        $this->assertSame([EmergencyRequest::FLAG_ACCURACY_UNKNOWN], $request->location_flags);
    }

    public function test_the_resident_may_attach_a_photo_after_dispatch_and_staff_can_open_it(): void
    {
        Notification::fake();
        Storage::fake('local');

        $resident = $this->resident();
        $request = $this->sos($resident);

        $this->assertNull($request->attachment_path);

        $this->actingAs($resident)
            ->post(route('sos.attach', $request), [
                'attachment' => UploadedFile::fake()->image('scene.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $request->refresh();
        Storage::disk('local')->assertExists($request->attachment_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sos.attachment_added']);

        $this->actingAs(User::factory()->create(['role' => UserRole::Official]))
            ->get(route('requests.attachment', $request))
            ->assertOk();
    }

    public function test_only_the_reporter_may_attach_to_their_sos(): void
    {
        Notification::fake();
        Storage::fake('local');

        $request = $this->sos($this->resident());

        $this->actingAs($this->resident())
            ->post(route('sos.attach', $request), [
                'attachment' => UploadedFile::fake()->image('scene.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    // ---- 4. Post-incident validation -----------------------------------------

    public function test_the_assigned_responder_records_the_outcome_and_it_is_audited(): void
    {
        Notification::fake();

        $medic = $this->responder(UserRole::Medical, ['medical']);
        $request = $this->closedSosFor($this->resident());
        $request->update(['sos_reason' => SosReason::Other, 'sos_reason_other' => 'Usok sa kusina']);
        $this->assign($request, $medic, completed: true);

        $this->actingAs($medic)
            ->patch(route('requests.updateOutcome', $request), ['outcome' => 'false_alarm'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame(IncidentOutcome::FalseAlarm, $request->outcome);
        $this->assertSame($medic->id, $request->outcome_set_by);
        $this->assertNotNull($request->outcome_set_at);

        $audit = AuditLog::where('action', 'incident.outcome_set')->sole();
        $this->assertSame($medic->id, $audit->user_id);
        $this->assertSame('false_alarm', $audit->after['outcome']);
        $this->assertSame('other', $audit->after['reason']);
        $this->assertSame('Usok sa kusina', $audit->after['reason_other']);
        $this->assertSame(1, $audit->after['reporter_false_alarm_count']);
        $this->assertStringContainsString('Other: Usok sa kusina', $audit->description);
    }

    public function test_an_official_may_record_any_outcome(): void
    {
        Notification::fake();

        $request = $this->closedSosFor($this->resident(), RequestStatus::Cancelled);

        $this->actingAs(User::factory()->create(['role' => UserRole::Official]))
            ->patch(route('requests.updateOutcome', $request), ['outcome' => 'test'])
            ->assertSessionHasNoErrors();

        $this->assertSame(IncidentOutcome::Test, $request->fresh()->outcome);
    }

    public function test_the_outcome_cannot_be_recorded_while_the_response_is_active(): void
    {
        Notification::fake();

        $request = $this->sos($this->resident());

        $this->actingAs(User::factory()->create(['role' => UserRole::Official]))
            ->patch(route('requests.updateOutcome', $request), ['outcome' => 'confirmed'])
            ->assertSessionHasErrors('outcome');

        $this->assertNull($request->fresh()->outcome);
    }

    public function test_a_responder_who_never_handled_the_incident_cannot_record_its_outcome(): void
    {
        Notification::fake();

        $request = $this->closedSosFor($this->resident());

        $this->actingAs($this->responder(UserRole::Medical, ['medical']))
            ->patch(route('requests.updateOutcome', $request), ['outcome' => 'false_alarm'])
            ->assertForbidden();

        $this->actingAs($request->resident)
            ->patch(route('requests.updateOutcome', $request), ['outcome' => 'confirmed'])
            ->assertForbidden();

        $this->assertNull($request->fresh()->outcome);
    }

    public function test_the_false_alarm_count_is_derived_from_outcome_history(): void
    {
        Notification::fake();

        $resident = $this->resident();

        foreach ([IncidentOutcome::FalseAlarm, IncidentOutcome::Confirmed, IncidentOutcome::FalseAlarm, IncidentOutcome::Test] as $outcome) {
            $this->closedSosFor($resident)->update(['outcome' => $outcome]);
        }

        $this->assertSame(2, $resident->falseAlarmCount());
    }

    // ---- 5. Cooldown override + trust tier -----------------------------------

    public function test_an_assigned_responder_can_clear_the_cooldown_during_an_active_response(): void
    {
        Notification::fake();

        $resident = $this->resident();
        $medic = $this->responder(UserRole::Medical, ['medical']);
        $request = $this->sos($resident);
        $request->update(['status' => RequestStatus::Assigned]);
        $this->assign($request, $medic);

        $this->actingAs($resident)->postJson(route('sos.store'), $this->payload())->assertStatus(429);

        $this->actingAs($medic)
            ->post(route('requests.clearSosCooldown', $request))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => 'sos.cooldown_cleared', 'user_id' => $medic->id]);

        $this->travel(2)->seconds();

        $this->actingAs($resident)->postJson(route('sos.store'), $this->payload())->assertCreated();
        $this->assertDatabaseCount('emergency_requests', 2);

        // The new SOS starts a fresh cooldown of its own (past the 3/min
        // request throttle, so the 429 is the cooldown's).
        $this->travel(61)->seconds();
        $this->actingAs($resident)
            ->postJson(route('sos.store'), $this->payload())
            ->assertStatus(429)
            ->assertJson(['cooldown' => true]);
    }

    public function test_the_cooldown_cannot_be_cleared_by_the_resident_or_an_unrelated_responder(): void
    {
        Notification::fake();

        $resident = $this->resident();
        $request = $this->sos($resident);

        $this->actingAs($resident)
            ->post(route('requests.clearSosCooldown', $request))
            ->assertForbidden();

        $this->actingAs($this->responder(UserRole::Medical, ['medical']))
            ->post(route('requests.clearSosCooldown', $request))
            ->assertForbidden();
    }

    public function test_the_cooldown_cannot_be_cleared_once_the_response_is_over(): void
    {
        Notification::fake();

        $request = $this->sos($this->resident());
        $request->update(['status' => RequestStatus::Resolved]);

        $this->actingAs(User::factory()->create(['role' => UserRole::Official]))
            ->post(route('requests.clearSosCooldown', $request))
            ->assertSessionHasErrors('cooldown');
    }

    public function test_three_false_alarms_flag_the_account_on_the_admin_dashboard_without_suspending_it(): void
    {
        Notification::fake();
        $this->withoutVite();

        $repeat = User::factory()->create(['role' => UserRole::Resident, 'name' => 'Repeat Reporter']);
        $twice = User::factory()->create(['role' => UserRole::Resident, 'name' => 'Twice Reporter']);

        foreach (range(1, 3) as $ignored) {
            $this->closedSosFor($repeat)->update(['outcome' => IncidentOutcome::FalseAlarm]);
        }

        foreach (range(1, 2) as $ignored) {
            $this->closedSosFor($twice)->update(['outcome' => IncidentOutcome::FalseAlarm]);
        }

        $this->assertTrue($repeat->isFlaggedForFalseAlarms());
        $this->assertFalse($twice->isFlaggedForFalseAlarms());

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Accounts Flagged for Review')
            ->assertSee('Repeat Reporter')
            ->assertViewHas('flaggedAccounts', fn ($accounts) => $accounts->pluck('id')->all() === [$repeat->id]
                && $accounts->first()->false_alarm_count === 3);

        // Flagged, not punished: the account can still raise an SOS.
        $this->assertSame(VerificationStatus::Verified, $repeat->fresh()->verification_status);
        $this->actingAs($repeat)->postJson(route('sos.store'), $this->payload())->assertCreated();
    }
}
