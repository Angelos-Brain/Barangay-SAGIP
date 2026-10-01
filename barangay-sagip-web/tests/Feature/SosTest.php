<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Enums\RequestStatus;
use App\Enums\UrgencyLevel;
use App\Enums\UserRole;
use App\Models\EmergencyRequest;
use App\Models\OutboundSmsMessage;
use App\Models\ResponsePersonnel;
use App\Models\User;
use App\Notifications\SosTriggered;
use App\Services\Sms\SemaphoreSmsDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Feature 2: SOS button and SMS fallback.
 */
class SosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'latitude' => 13.5925,
            'longitude' => 124.2049,
            'accuracy' => 12.5,
            'triggered_at' => now()->toIso8601String(),
            'reason' => 'medical',
            'device_id' => 'device-abc123',
        ];
    }

    private function medicalResponder(): User
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

        return $user;
    }

    public function test_sos_creates_a_critical_incident_with_the_captured_location(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);

        $response = $this->actingAs($resident)
            ->postJson(route('sos.store'), $this->payload())
            ->assertCreated()
            ->assertJson(['ok' => true, 'duplicate' => false]);

        $request = EmergencyRequest::sole();

        $this->assertSame($resident->id, $request->resident_id);
        $this->assertSame('sos', $request->source);
        $this->assertSame('online', $request->sos_channel);
        $this->assertSame(UrgencyLevel::Critical, $request->urgency);
        $this->assertTrue($request->needs_review);
        $this->assertSame(RequestStatus::NeedsReview, $request->status);
        $this->assertSame('13.5925000', (string) $request->latitude);
        $this->assertSame('124.2049000', (string) $request->longitude);
        $this->assertSame('12.50', (string) $request->location_accuracy_meters);
        $this->assertSame($request->id, $response->json('request_id'));
    }

    public function test_sos_records_who_pressed_it_and_when_in_the_audit_trail(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);

        $this->actingAs($resident)->postJson(route('sos.store'), $this->payload());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'sos.triggered',
            'user_id' => $resident->id,
        ]);
    }

    public function test_sos_notifies_matching_responders_and_officials_in_app(): void
    {
        Notification::fake();

        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $official = User::factory()->create(['role' => UserRole::Official]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $responder = $this->medicalResponder();

        $this->actingAs($resident)
            ->postJson(route('sos.store'), $this->payload())
            ->assertCreated()
            ->assertJson(['notified_responders' => 3]);

        Notification::assertSentTo([$official, $admin, $responder], SosTriggered::class);
        Notification::assertNotSentTo([$resident], SosTriggered::class);
    }

    public function test_an_unavailable_responder_is_not_alerted(): void
    {
        Notification::fake();

        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $offDuty = User::factory()->create(['role' => UserRole::Medical]);
        ResponsePersonnel::create([
            'user_id' => $offDuty->id,
            'name' => $offDuty->name,
            'specializations' => ['medical'],
            'is_available' => false,
            'latitude' => 13.5920,
            'longitude' => 124.2050,
        ]);

        $this->actingAs($resident)->postJson(route('sos.store'), $this->payload());

        Notification::assertNotSentTo([$offDuty], SosTriggered::class);
    }

    public function test_a_second_sos_inside_the_cooldown_is_refused_without_opening_an_incident(): void
    {
        Notification::fake();

        $resident = User::factory()->create(['role' => UserRole::Resident]);

        $first = $this->actingAs($resident)->postJson(route('sos.store'), $this->payload())->assertCreated();

        Notification::fake();

        $this->actingAs($resident)
            ->postJson(route('sos.store'), $this->payload())
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJson([
                'ok' => false,
                'cooldown' => true,
                'request_id' => $first->json('request_id'),
            ]);

        $this->assertDatabaseCount('emergency_requests', 1);
        Notification::assertNothingSent();
    }

    public function test_the_cooldown_lapses_after_two_minutes(): void
    {
        Notification::fake();

        $resident = User::factory()->create(['role' => UserRole::Resident]);

        $this->actingAs($resident)->postJson(route('sos.store'), $this->payload())->assertCreated();

        $this->travel(121)->seconds();

        $this->actingAs($resident)->postJson(route('sos.store'), $this->payload())->assertCreated();

        $this->assertDatabaseCount('emergency_requests', 2);
    }

    public function test_sos_requires_a_location(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident]);

        $this->actingAs($resident)
            ->postJson(route('sos.store'), ['accuracy' => 10])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);

        $this->assertDatabaseCount('emergency_requests', 0);
    }

    public function test_an_unverified_resident_cannot_trigger_an_sos(): void
    {
        $resident = User::factory()->pendingVerification()->create(['role' => UserRole::Resident]);

        $this->actingAs($resident)
            ->postJson(route('sos.store'), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('emergency_requests', 0);
    }

    public function test_a_guest_cannot_trigger_an_sos(): void
    {
        $this->postJson(route('sos.store'), $this->payload())->assertUnauthorized();

        $this->assertDatabaseCount('emergency_requests', 0);
    }

    public function test_the_sms_fallback_records_an_outbound_message_against_the_incident(): void
    {
        Notification::fake();

        $resident = User::factory()->create([
            'role' => UserRole::Resident,
            'name' => 'Maria Santos',
            'phone_number' => '09170000003',
        ]);

        $this->actingAs($resident)
            ->postJson(route('sos.smsFallback'), $this->payload())
            ->assertCreated()
            ->assertJson([
                'ok' => true,
                'sms_status' => OutboundSmsMessage::STATUS_SENT,
                'sms_driver' => 'log',
                'recipient' => config('sagip.sos.hotline_number'),
            ]);

        $request = EmergencyRequest::sole();
        $message = OutboundSmsMessage::sole();

        $this->assertSame('sms_fallback', $request->source);
        $this->assertSame('sms', $request->sos_channel);
        $this->assertSame($request->id, $message->emergency_request_id);
        $this->assertSame($resident->id, $message->user_id);
        $this->assertStringContainsString('Maria Santos', $message->body);
        $this->assertStringContainsString('13.5925', $message->body);

        $this->assertDatabaseHas('audit_logs', ['action' => 'sos.sms_fallback']);
    }

    public function test_the_fallback_attaches_to_the_incident_the_timed_out_call_already_filed(): void
    {
        Notification::fake();

        $resident = User::factory()->create(['role' => UserRole::Resident]);

        // The online attempt actually reached the server; the browser just never
        // saw the reply and fell back.
        $this->actingAs($resident)->postJson(route('sos.store'), $this->payload())->assertCreated();
        $this->actingAs($resident)->postJson(route('sos.smsFallback'), $this->payload())->assertCreated();

        $this->assertDatabaseCount('emergency_requests', 1);
        $this->assertSame(
            EmergencyRequest::sole()->id,
            OutboundSmsMessage::sole()->emergency_request_id
        );
    }

    public function test_a_gateway_failure_is_recorded_and_reported_rather_than_thrown(): void
    {
        Notification::fake();

        config()->set('sagip.sms.driver', 'semaphore');
        config()->set('sagip.sms.semaphore.api_key', 'test-key');
        $this->app->forgetInstance(SmsSender::class);
        $this->app->singleton(SmsSender::class, fn () => new SemaphoreSmsDriver);

        Http::fake([
            '*' => Http::response('gateway exploded', 500),
        ]);

        $resident = User::factory()->create(['role' => UserRole::Resident]);

        $this->actingAs($resident)
            ->postJson(route('sos.smsFallback'), $this->payload())
            ->assertStatus(502)
            ->assertJson(['ok' => false, 'sms_status' => OutboundSmsMessage::STATUS_FAILED]);

        $message = OutboundSmsMessage::sole();

        $this->assertSame('semaphore', $message->driver);
        $this->assertStringContainsString('HTTP 500', $message->failure_reason);
        // The incident itself still exists — a dead gateway must not lose the SOS.
        $this->assertDatabaseCount('emergency_requests', 1);
    }

    public function test_a_missing_gateway_key_fails_closed_without_calling_out(): void
    {
        Notification::fake();
        Http::fake();

        config()->set('sagip.sms.driver', 'semaphore');
        config()->set('sagip.sms.semaphore.api_key', null);
        $this->app->forgetInstance(SmsSender::class);
        $this->app->singleton(SmsSender::class, fn () => new SemaphoreSmsDriver);

        $resident = User::factory()->create(['role' => UserRole::Resident]);

        $this->actingAs($resident)
            ->postJson(route('sos.smsFallback'), $this->payload())
            ->assertStatus(502);

        $this->assertStringContainsString(
            'SAGIP_SEMAPHORE_API_KEY',
            OutboundSmsMessage::sole()->failure_reason
        );

        Http::assertNothingSent();
    }

    public function test_the_sos_button_renders_for_a_verified_user_but_not_a_pending_one(): void
    {
        $this->withoutVite();

        $verified = User::factory()->create(['role' => UserRole::Resident]);
        $this->actingAs($verified)
            ->get(route('requests.create'))
            ->assertOk()
            ->assertSee('Send an emergency SOS with my location');

        $pending = User::factory()->pendingVerification()->create(['role' => UserRole::Resident]);
        $this->actingAs($pending)
            ->get(route('account.verification.pending'))
            ->assertOk()
            ->assertDontSee('Send an emergency SOS with my location');
    }

    public function test_the_sos_appears_on_the_response_map(): void
    {
        Notification::fake();

        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $official = User::factory()->create(['role' => UserRole::Official]);

        $this->actingAs($resident)->postJson(route('sos.store'), $this->payload());

        $this->actingAs($official)
            ->getJson(route('map.data'))
            ->assertOk()
            ->assertJsonCount(1, 'requests');
    }
}
