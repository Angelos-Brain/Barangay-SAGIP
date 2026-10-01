<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\EmergencyRequest;
use App\Models\ResponsePersonnel;
use App\Models\User;
use App\Notifications\IncidentReported;
use App\Notifications\RequestStatusUpdated;
use App\Notifications\SosTriggered;
use App\Services\IncidentAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Feature 8: Emergency email alerts to officials and matching on-duty personnel.
 */
class IncidentEmailAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * @param  list<string>  $specializations
     */
    private function responder(UserRole $role, array $specializations, bool $onDuty = true): User
    {
        $user = User::factory()->create(['role' => $role]);

        ResponsePersonnel::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'specializations' => $specializations,
            'is_available' => $onDuty,
            'latitude' => 13.5920,
            'longitude' => 124.2050,
        ]);

        return $user;
    }

    private function fileReport(User $resident, string $description = 'May sunog sa kabilang kalye, kumakalat na ang apoy.'): void
    {
        $this->actingAs($resident)->post(route('requests.store'), [
            'description' => $description,
            'latitude' => 13.5925,
            'longitude' => 124.2049,
        ])->assertRedirect();
    }

    public function test_a_new_report_emails_officials_and_the_matching_on_duty_responder(): void
    {
        Notification::fake();

        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $official = User::factory()->create(['role' => UserRole::Official]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $fireResponder = $this->responder(UserRole::FireDisaster, ['fire']);
        $medicalResponder = $this->responder(UserRole::Medical, ['medical']);

        $this->fileReport($resident);

        // The classifier is unreachable in tests, so the report stays
        // unclassified and every specialization is alerted — which is the
        // correct fail-open behaviour for an un-triaged emergency.
        Notification::assertSentTo([$official, $admin, $fireResponder, $medicalResponder], IncidentReported::class);
    }

    public function test_the_alert_email_reaches_the_recipient_by_mail_channel(): void
    {
        Notification::fake();

        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $official = User::factory()->create(['role' => UserRole::Official]);

        $this->fileReport($resident);

        Notification::assertSentTo(
            $official,
            IncidentReported::class,
            function (IncidentReported $notification, array $channels) use ($official) {
                $this->assertContains('mail', $channels);
                $this->assertContains('database', $channels);

                $mail = $notification->toMail($official);
                $this->assertStringContainsString('New', $mail->subject);

                return true;
            }
        );
    }

    public function test_the_reporter_does_not_receive_the_responder_alert(): void
    {
        Notification::fake();

        $resident = User::factory()->create(['role' => UserRole::Resident]);
        User::factory()->create(['role' => UserRole::Official]);

        $this->fileReport($resident);

        Notification::assertNotSentTo($resident, IncidentReported::class);
    }

    public function test_an_off_duty_responder_is_not_alerted(): void
    {
        Notification::fake();

        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $onDuty = $this->responder(UserRole::Medical, ['medical'], onDuty: true);
        $offDuty = $this->responder(UserRole::Medical, ['medical'], onDuty: false);

        $this->fileReport($resident);

        Notification::assertSentTo($onDuty, IncidentReported::class);
        Notification::assertNotSentTo($offDuty, IncidentReported::class);
    }

    public function test_only_responders_whose_tag_covers_the_category_are_alerted(): void
    {
        Notification::fake();

        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $official = User::factory()->create(['role' => UserRole::Official]);
        $medic = $this->responder(UserRole::Medical, ['medical']);
        $fire = $this->responder(UserRole::FireDisaster, ['fire']);
        $weather = $this->responder(UserRole::Weather, ['weather']);

        // A classified medical incident, built directly so the category is known.
        $request = EmergencyRequest::create([
            'resident_id' => $resident->id,
            'description' => 'Hindi humihinga ang sanggol.',
            'category' => 'medical',
            'category_confidence' => 0.95,
            'urgency' => 'critical',
            'urgency_confidence' => 0.95,
            'latitude' => 13.5925,
            'longitude' => 124.2049,
            'status' => 'validated',
        ]);

        app(IncidentAlertService::class)->alert($request, new IncidentReported($request));

        Notification::assertSentTo([$official, $medic], IncidentReported::class);
        Notification::assertNotSentTo([$fire, $weather], IncidentReported::class);
    }

    public function test_a_weather_tagged_responder_is_alerted_about_a_disaster_incident(): void
    {
        Notification::fake();

        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $weather = $this->responder(UserRole::Weather, ['weather']);
        $medic = $this->responder(UserRole::Medical, ['medical']);

        $request = EmergencyRequest::create([
            'resident_id' => $resident->id,
            'description' => 'Bumabaha na hanggang baywang sa Purok 2.',
            'category' => 'disaster',
            'category_confidence' => 0.9,
            'urgency' => 'critical',
            'urgency_confidence' => 0.9,
            'latitude' => 13.5925,
            'longitude' => 124.2049,
            'status' => 'validated',
        ]);

        app(IncidentAlertService::class)->alert($request, new IncidentReported($request));

        Notification::assertSentTo($weather, IncidentReported::class);
        Notification::assertNotSentTo($medic, IncidentReported::class);
    }

    public function test_a_responder_with_several_tags_is_alerted_for_each_of_them(): void
    {
        Notification::fake();

        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $multi = $this->responder(UserRole::FireDisaster, ['fire', 'medical']);

        foreach (['fire', 'medical'] as $category) {
            $request = EmergencyRequest::create([
                'resident_id' => $resident->id,
                'description' => 'Test incident for '.$category,
                'category' => $category,
                'category_confidence' => 0.9,
                'urgency' => 'high',
                'urgency_confidence' => 0.9,
                'latitude' => 13.5925,
                'longitude' => 124.2049,
                'status' => 'validated',
            ]);

            app(IncidentAlertService::class)->alert($request, new IncidentReported($request));
        }

        Notification::assertSentToTimes($multi, IncidentReported::class, 2);
    }

    public function test_an_sos_alerts_the_same_recipients_by_email(): void
    {
        Notification::fake();

        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $official = User::factory()->create(['role' => UserRole::Official]);
        $medic = $this->responder(UserRole::Medical, ['medical']);

        $this->actingAs($resident)->postJson(route('sos.store'), [
            'latitude' => 13.5925,
            'longitude' => 124.2049,
            'reason' => 'other',
            'reason_other' => 'Hindi ko alam',
        ])->assertCreated();

        // An "Other" SOS carries no category, so every specialization is alerted.
        Notification::assertSentTo([$official, $medic], SosTriggered::class);
        Notification::assertNotSentTo($resident, SosTriggered::class);
    }

    public function test_the_alert_email_names_vulnerable_household_members(): void
    {
        $resident = User::factory()->create(['role' => UserRole::Resident, 'name' => 'Lola Remedios']);
        $resident->residentProfile()->create([
            'full_name' => 'Lola Remedios',
            'address' => '9, Rizal Street, Calatagan Tibang, Virac, Catanduanes',
            'household_members_count' => 2,
            'vulnerability_tags' => ['elderly', 'pwd'],
        ]);
        $official = User::factory()->create(['role' => UserRole::Official]);

        $request = EmergencyRequest::create([
            'resident_id' => $resident->id,
            'description' => 'Bumabaha na sa loob ng bahay.',
            'category' => 'disaster',
            'category_confidence' => 0.9,
            'urgency' => 'critical',
            'urgency_confidence' => 0.9,
            'latitude' => 13.5925,
            'longitude' => 124.2049,
            'status' => 'validated',
        ]);

        $mail = (new IncidentReported($request->fresh()))->toMail($official);
        $body = implode(' ', $mail->introLines);

        $this->assertStringContainsString('Vulnerable household members', $body);
        $this->assertStringContainsString('Elderly', $body);
        $this->assertStringContainsString('PWD', $body);
    }

    public function test_the_alert_is_recorded_in_the_audit_trail_with_its_recipient_count(): void
    {
        Notification::fake();

        $resident = User::factory()->create(['role' => UserRole::Resident]);
        User::factory()->create(['role' => UserRole::Official]);
        $this->responder(UserRole::Medical, ['medical']);

        $this->fileReport($resident);

        $this->assertDatabaseHas('audit_logs', ['action' => 'incident.alerted']);

        $entry = AuditLog::where('action', 'incident.alerted')->latest('id')->sole();
        $this->assertSame(2, $entry->after['recipient_count']);
    }

    public function test_having_nobody_to_alert_is_itself_recorded(): void
    {
        Notification::fake();

        $resident = User::factory()->create(['role' => UserRole::Resident]);

        // No officials, no admins, no on-duty responders exist.
        $this->fileReport($resident);

        $this->assertDatabaseHas('audit_logs', ['action' => 'incident.alert_no_recipients']);

        // The reporter still gets their own status notification; what must not
        // happen is the responder alert going out to nobody-in-particular.
        Notification::assertNotSentTo($resident, IncidentReported::class);
        Notification::assertSentTo($resident, RequestStatusUpdated::class);
    }
}
