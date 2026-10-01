<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Enums\PersonnelAccountStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\OutboundSmsMessage;
use App\Models\ResponsePersonnel;
use App\Models\User;
use App\Services\PersonnelLoginCodeService;
use App\Services\Sms\LogSmsDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Officials add a responder by mobile number; the responder claims the account
 * in First Login by entering that number and the one-time code texted to it.
 */
class PersonnelMobileLoginTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{recipient: string, body: string}> */
    private array $sentSms = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $sent = &$this->sentSms;
        $this->app->instance(SmsSender::class, new class($sent) implements SmsSender
        {
            /** @param  list<array{recipient: string, body: string}>  $sent */
            public function __construct(private array &$sent) {}

            public function name(): string
            {
                return 'fake';
            }

            public function send(string $recipient, string $body): array
            {
                $this->sent[] = ['recipient' => $recipient, 'body' => $body];

                return ['sent' => true, 'reference' => null, 'error' => null];
            }
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function personnelForm(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana Reyes',
            'email' => 'ana.reyes@example.com',
            'phone_number' => '+63 917 555 1234',
            'specializations' => ['medical'],
            'latitude' => 13.5920,
            'longitude' => 124.2050,
        ], $overrides);
    }

    private function addPersonnel(array $overrides = []): User
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Official]))
            ->post(route('personnel.store'), $this->personnelForm($overrides))
            ->assertRedirect(route('personnel.index'))
            ->assertSessionHasNoErrors();

        auth()->logout();

        return User::where('email', $overrides['email'] ?? 'ana.reyes@example.com')->sole();
    }

    private function lastCode(): string
    {
        $body = collect($this->sentSms)->last()['body'];
        preg_match('/code is (\d{6})/', $body, $matches);

        return $matches[1];
    }

    public function test_adding_personnel_creates_an_unclaimed_account_tied_to_their_mobile_number(): void
    {
        $user = $this->addPersonnel();

        $this->assertSame('09175551234', $user->phone_number);
        $this->assertSame(UserRole::Medical, $user->role);
        $this->assertSame(PersonnelAccountStatus::Unclaimed, $user->personnelAccountStatus());
        $this->assertSame($user->id, ResponsePersonnel::sole()->user_id);

        $this->assertSame('09175551234', $this->sentSms[0]['recipient']);
        $this->assertStringContainsString(route('personnel.setup'), $this->sentSms[0]['body']);
    }

    public function test_the_role_follows_the_specialization_tags(): void
    {
        $this->assertSame(UserRole::FireDisaster, $this->addPersonnel([
            'email' => 'fire@example.com', 'phone_number' => '09175550001', 'specializations' => ['fire', 'disaster'],
        ])->role);

        $this->assertSame(UserRole::Personnel, $this->addPersonnel([
            'email' => 'multi@example.com', 'phone_number' => '09175550002', 'specializations' => ['medical', 'fire'],
        ])->role);
    }

    public function test_adding_personnel_requires_a_valid_unused_mobile_number_and_email(): void
    {
        User::factory()->create(['phone_number' => '09175551234']);
        $official = User::factory()->create(['role' => UserRole::Official]);

        $this->actingAs($official)
            ->post(route('personnel.store'), $this->personnelForm(['phone_number' => '0917 555 1234']))
            ->assertSessionHasErrors('phone_number');

        $this->actingAs($official)
            ->post(route('personnel.store'), $this->personnelForm(['phone_number' => '12345', 'email' => '']))
            ->assertSessionHasErrors(['phone_number', 'email']);

        $this->assertDatabaseCount('response_personnel', 0);
    }

    public function test_first_login_verifies_the_number_and_continues_to_the_password_step(): void
    {
        $user = $this->addPersonnel();

        $this->post(route('personnel.setup.send'), ['phone_number' => '0917-555-1234'])
            ->assertRedirect(route('personnel.setup.verify'));

        $this->get(route('personnel.setup.verify'))->assertOk()->assertSee('0917')->assertSee('Verify');

        $this->post(route('personnel.setup.check'), ['code' => $this->lastCode()])
            ->assertRedirect(route('account.setup.password'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(PersonnelAccountStatus::PhoneVerified, $user->fresh()->personnelAccountStatus());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_code_sent', 'auditable_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_code_verified', 'auditable_id' => $user->id]);
    }

    public function test_an_unregistered_number_is_told_to_contact_the_admin_and_gets_no_sms(): void
    {
        $this->addPersonnel();
        $this->sentSms = [];

        $this->post(route('personnel.setup.send'), ['phone_number' => '09999999999'])
            ->assertSessionHasErrors(['phone_number' => 'This number is not registered as barangay personnel. Contact your barangay admin.']);

        $this->assertSame([], $this->sentSms);
        $this->get(route('personnel.setup.verify'))->assertRedirect(route('personnel.setup'));
    }

    public function test_an_invalid_number_is_rejected_before_lookup(): void
    {
        $this->post(route('personnel.setup.send'), ['phone_number' => '12345'])
            ->assertSessionHasErrors('phone_number');

        $this->assertSame([], $this->sentSms);
    }

    public function test_residents_officials_and_removed_personnel_cannot_start_first_login(): void
    {
        User::factory()->create(['role' => UserRole::Resident, 'phone_number' => '09171110001']);
        User::factory()->create(['role' => UserRole::Official, 'phone_number' => '09171110002']);
        $removed = $this->addPersonnel(['email' => 'gone@example.com', 'phone_number' => '09171110003']);
        $removed->responsePersonnel->delete();
        $this->sentSms = [];

        foreach (['09171110001', '09171110002', '09171110003'] as $phone) {
            $this->post(route('personnel.setup.send'), ['phone_number' => $phone])->assertSessionHasErrors('phone_number');
        }

        $this->assertSame([], $this->sentSms);
    }

    public function test_an_account_that_is_already_active_is_sent_to_sign_in(): void
    {
        $this->addPersonnel()->forceFill([
            'phone_verified_at' => now(),
            'account_setup_completed_at' => now(),
            'email_verified_at' => now(),
        ])->save();
        $this->sentSms = [];

        $this->post(route('personnel.setup.send'), ['phone_number' => '09175551234'])
            ->assertSessionHasErrors('phone_number');

        $this->assertSame([], $this->sentSms);
    }

    public function test_the_code_is_never_stored_in_plain_text(): void
    {
        $this->addPersonnel();

        $this->post(route('personnel.setup.send'), ['phone_number' => '09175551234']);
        $code = $this->lastCode();

        $stored = OutboundSmsMessage::where('purpose', OutboundSmsMessage::PURPOSE_LOGIN_CODE)->sole();
        $this->assertStringNotContainsString($code, $stored->body);
        $this->assertStringNotContainsString($code, json_encode(AuditLog::all()->toArray()));
    }

    public function test_a_code_works_only_once(): void
    {
        $this->addPersonnel();

        $this->post(route('personnel.setup.send'), ['phone_number' => '09175551234']);
        $code = $this->lastCode();
        $this->post(route('personnel.setup.check'), ['code' => $code])->assertRedirect(route('account.setup.password'));

        auth()->logout();
        $this->withSession(['personnel_setup.phone' => '09175551234'])
            ->post(route('personnel.setup.check'), ['code' => $code])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_wrong_code_is_rejected_and_five_wrong_codes_void_it(): void
    {
        $this->addPersonnel();

        $this->post(route('personnel.setup.send'), ['phone_number' => '09175551234']);
        $code = $this->lastCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, 5) as $ignored) {
            $this->post(route('personnel.setup.check'), ['code' => $wrong])->assertSessionHasErrors('code');
        }

        // The fifth wrong guess voided the code, so even the right one now fails.
        $this->post(route('personnel.setup.check'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_code_failed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_code_locked']);
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $this->addPersonnel();

        $this->post(route('personnel.setup.send'), ['phone_number' => '09175551234']);
        $code = $this->lastCode();

        $this->travel(6)->minutes();

        $this->post(route('personnel.setup.check'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_new_code_can_only_be_requested_once_a_minute(): void
    {
        $this->addPersonnel();
        $this->sentSms = [];

        $this->post(route('personnel.setup.send'), ['phone_number' => '09175551234']);
        $this->post(route('personnel.setup.resend'))->assertSessionHasErrors('code');
        $this->assertCount(1, $this->sentSms);

        $this->travel(61)->seconds();
        $this->post(route('personnel.setup.resend'))->assertSessionHas('status');
        $this->assertCount(2, $this->sentSms);
    }

    public function test_code_requests_are_rate_limited_per_phone(): void
    {
        $this->addPersonnel();

        foreach (range(1, 3) as $ignored) {
            $this->post(route('personnel.setup.send'), ['phone_number' => '09175551234'])->assertRedirect();
        }

        $this->post(route('personnel.setup.send'), ['phone_number' => '09175551234'])->assertTooManyRequests();
    }

    public function test_the_log_sms_mock_never_sends_codes_in_production(): void
    {
        $user = $this->addPersonnel();
        $this->app->instance(SmsSender::class, new LogSmsDriver);
        $this->app['env'] = 'production';

        $this->assertNull(app(PersonnelLoginCodeService::class)->send($user));

        $this->assertDatabaseMissing('outbound_sms_messages', ['purpose' => OutboundSmsMessage::PURPOSE_LOGIN_CODE]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_code_undeliverable', 'auditable_id' => $user->id]);
    }

    public function test_changing_the_mobile_number_changes_the_login_number(): void
    {
        $user = $this->addPersonnel();
        $personnel = $user->responsePersonnel;

        $this->actingAs(User::factory()->create(['role' => UserRole::Official]))
            ->put(route('personnel.update', $personnel), [
                'name' => $personnel->name,
                'specializations' => ['medical'],
                'phone_number' => '09175559999',
                'latitude' => 13.5920,
                'longitude' => 124.2050,
                'is_available' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('09175559999', $user->fresh()->phone_number);
    }
}
