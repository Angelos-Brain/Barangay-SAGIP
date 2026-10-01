<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Enums\UserRole;
use App\Http\Controllers\Auth\PersonnelLoginController;
use App\Models\AuditLog;
use App\Models\ResponsePersonnel;
use App\Models\User;
use App\Services\PersonnelLoginCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Subsequent personnel logins (email or mobile + password), the lockout,
 * resuming an unfinished First Login, and Forgot Password.
 */
class PersonnelLoginTest extends TestCase
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

    private function responder(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => UserRole::Medical,
            'email' => 'ana.reyes@example.com',
            'phone_number' => '09175551234',
            'password' => Hash::make('bantay-2026!'),
        ], $attributes));

        ResponsePersonnel::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'specialization' => 'medical',
            'is_available' => true,
            'current_workload' => 0,
            'latitude' => 13.5925,
            'longitude' => 124.2049,
        ]);

        return $user;
    }

    private function lastCode(): string
    {
        preg_match('/code is (\d{6})/', collect($this->sentSms)->last()['body'], $matches);

        return $matches[1];
    }

    public function test_the_login_page_renders_with_back_forgot_and_first_login_links(): void
    {
        $this->get(route('personnel.login'))->assertOk()
            ->assertSee('Email or Mobile Number')
            ->assertSee('Back to home')
            ->assertSee(route('personnel.password.request'))
            ->assertSee(route('personnel.setup'));
    }

    public function test_an_active_responder_signs_in_with_email_or_mobile_number(): void
    {
        $user = $this->responder();

        $this->post(route('personnel.login.store'), ['identifier' => 'Ana.Reyes@example.com', 'password' => 'bantay-2026!'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->post(route('admin.logout'));

        foreach (['09175551234', '+63 917-555-1234'] as $phone) {
            $this->post(route('personnel.login.store'), ['identifier' => $phone, 'password' => 'bantay-2026!'])
                ->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs($user);
            $this->post(route('admin.logout'));
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_every_failure_shows_the_same_generic_message(): void
    {
        $this->responder();
        User::factory()->create(['role' => UserRole::Resident, 'email' => 'resident@example.com', 'password' => Hash::make('bantay-2026!')]);

        $attempts = [
            ['identifier' => 'ana.reyes@example.com', 'password' => 'wrong-password'],
            ['identifier' => 'nobody@example.com', 'password' => 'bantay-2026!'],
            ['identifier' => '09999999999', 'password' => 'bantay-2026!'],
            ['identifier' => 'not a phone', 'password' => 'bantay-2026!'],
            ['identifier' => 'resident@example.com', 'password' => 'bantay-2026!'],
        ];

        foreach ($attempts as $credentials) {
            $this->post(route('personnel.login.store'), $credentials)
                ->assertSessionHasErrors(['identifier' => PersonnelLoginController::FAILED_MESSAGE]);
        }

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.personnel_login_failed']);
    }

    public function test_an_unclaimed_account_cannot_sign_in_with_a_password(): void
    {
        // The admin-created placeholder hash is never a usable password.
        $this->responder(['phone_verified_at' => null, 'account_setup_completed_at' => null, 'email_verified_at' => null, 'password' => Hash::make('guess')]);

        $this->post(route('personnel.login.store'), ['identifier' => '09175551234', 'password' => 'guess'])
            ->assertSessionHasErrors(['identifier' => PersonnelLoginController::FAILED_MESSAGE]);
        $this->assertGuest();
    }

    public function test_five_failures_lock_the_identifier_for_fifteen_minutes(): void
    {
        $user = $this->responder();

        foreach (range(1, 5) as $ignored) {
            $this->post(route('personnel.login.store'), ['identifier' => '09175551234', 'password' => 'wrong-password'])
                ->assertSessionHasErrors('identifier');
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.personnel_login_locked', 'auditable_id' => $user->id]);

        // Even the right password is refused while locked.
        $this->travel(1)->minutes();
        $this->post(route('personnel.login.store'), ['identifier' => '0917 555 1234', 'password' => 'bantay-2026!'])
            ->assertSessionHasErrors('identifier');
        $this->assertStringContainsString('Too many failed attempts', session('errors')->first('identifier'));
        $this->assertGuest();

        $this->travel(15)->minutes();
        $this->post(route('personnel.login.store'), ['identifier' => '09175551234', 'password' => 'bantay-2026!'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_requests_are_rate_limited(): void
    {
        $this->responder();

        foreach (range(1, 10) as $ignored) {
            $this->post(route('personnel.login.store'), ['identifier' => 'someone@example.com', 'password' => 'x']);
        }

        $this->post(route('personnel.login.store'), ['identifier' => 'someone@example.com', 'password' => 'x'])
            ->assertTooManyRequests();
    }

    public function test_an_account_awaiting_email_confirmation_resumes_at_the_email_step(): void
    {
        $user = $this->responder(['email_verified_at' => null]);

        $this->post(route('personnel.login.store'), ['identifier' => 'ana.reyes@example.com', 'password' => 'bantay-2026!'])
            ->assertRedirect(route('account.setup.email'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertRedirect(route('account.setup.email'));
    }

    public function test_a_phone_verified_account_without_a_password_resumes_at_the_password_step_via_first_login(): void
    {
        $user = $this->responder(['account_setup_completed_at' => null, 'email_verified_at' => null]);

        $this->post(route('personnel.setup.send'), ['phone_number' => '09175551234'])->assertRedirect(route('personnel.setup.verify'));
        $this->post(route('personnel.setup.check'), ['code' => $this->lastCode()])
            ->assertRedirect(route('account.setup.password'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_personnel_are_turned_away_from_the_staff_login(): void
    {
        $this->responder();

        $this->post(route('admin.login.store'), ['email' => 'ana.reyes@example.com', 'password' => 'bantay-2026!'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->get(route('admin.login'))->assertSee(route('personnel.login'));
    }

    public function test_forgot_password_resets_it_after_sms_verification(): void
    {
        $user = $this->responder();

        $this->get(route('personnel.password.request'))->assertOk()->assertSee('Back to sign in');

        $this->post(route('personnel.password.send'), ['phone_number' => '0917 555 1234'])
            ->assertRedirect(route('personnel.password.verify'));
        $this->assertStringContainsString('password reset code', $this->sentSms[0]['body']);

        $this->get(route('personnel.password.reset'))->assertRedirect(route('personnel.password.request'));

        $this->post(route('personnel.password.check'), ['code' => $this->lastCode()])
            ->assertRedirect(route('personnel.password.reset'));

        $this->post(route('personnel.password.update'), ['password' => '09175551234', 'password_confirmation' => '09175551234'])
            ->assertSessionHasErrors('password');

        $this->post(route('personnel.password.update'), ['password' => 'new-bantay-99', 'password_confirmation' => 'new-bantay-99'])
            ->assertRedirect(route('personnel.login'));

        $this->assertTrue(Hash::check('new-bantay-99', $user->refresh()->password));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_reset', 'auditable_id' => $user->id]);

        $this->post(route('personnel.login.store'), ['identifier' => '09175551234', 'password' => 'new-bantay-99'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_forgot_password_does_not_reveal_unknown_numbers_or_text_them(): void
    {
        $this->post(route('personnel.password.send'), ['phone_number' => '09999999999'])
            ->assertRedirect(route('personnel.password.verify'))
            ->assertSessionHas('status', 'If this number belongs to a registered responder, a reset code has been sent to it.');

        $this->assertSame([], $this->sentSms);
    }

    public function test_a_first_login_code_cannot_be_used_to_reset_a_password(): void
    {
        $this->responder(['email_verified_at' => null]);

        // A setup code is issued for the same number...
        app(PersonnelLoginCodeService::class)->send(User::sole());
        $setupCode = $this->lastCode();

        $this->withSession(['personnel_reset.phone' => '09175551234'])
            ->post(route('personnel.password.check'), ['code' => $setupCode])
            ->assertSessionHasErrors('code');
    }

    public function test_the_audit_trail_never_contains_codes_or_passwords(): void
    {
        $user = $this->responder();

        $this->post(route('personnel.login.store'), ['identifier' => '09175551234', 'password' => 'wrong-guess-123']);
        $this->post(route('personnel.password.send'), ['phone_number' => '09175551234']);
        $code = $this->lastCode();
        $this->post(route('personnel.password.check'), ['code' => $code]);
        $this->post(route('personnel.password.update'), ['password' => 'new-bantay-99', 'password_confirmation' => 'new-bantay-99']);
        $this->post(route('personnel.login.store'), ['identifier' => '09175551234', 'password' => 'new-bantay-99']);

        $trail = json_encode(AuditLog::all()->toArray());

        foreach ([$code, 'wrong-guess-123', 'new-bantay-99', 'bantay-2026!', $user->refresh()->password] as $secret) {
            $this->assertStringNotContainsString($secret, $trail);
        }

        foreach (['auth.login_code_sent', 'auth.login_code_verified', 'auth.password_reset', 'auth.personnel_login_failed', 'auth.login'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action]);
        }

        $entry = AuditLog::where('action', 'auth.personnel_login_failed')->first();
        $this->assertSame($user->id, $entry->auditable_id);
        $this->assertNotNull($entry->ip_address);
        $this->assertNotNull($entry->created_at);
    }
}
