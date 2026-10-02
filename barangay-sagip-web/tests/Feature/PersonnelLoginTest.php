<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Enums\UserRole;
use App\Http\Controllers\Auth\PersonnelLoginController;
use App\Models\AuditLog;
use App\Models\ResponsePersonnel;
use App\Models\User;
use App\Notifications\ConfirmPersonnelEmail;
use App\Notifications\ResetPersonnelPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Subsequent personnel logins (email + password only), the lockout, accounts
 * that still need to verify their email, and Forgot Password by emailed link.
 * No SMS is sent by any of it.
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
        Notification::fake();

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

    protected function tearDown(): void
    {
        $this->assertSame([], $this->sentSms, 'No SMS should be sent by personnel sign-in or password reset.');

        parent::tearDown();
    }

    private function responder(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => UserRole::Medical,
            'email' => 'ana.reyes@gmail.com',
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

    private function latestResetLink(User $user): string
    {
        return Notification::sent($user, ResetPersonnelPassword::class)->last()->resetUrl;
    }

    public function test_the_login_page_asks_for_email_and_password_only(): void
    {
        $this->get(route('personnel.login'))->assertOk()
            ->assertSee('Sign in with your email and password.')
            ->assertDontSee('Mobile Number')
            ->assertSee('Back to home')
            ->assertSee(route('personnel.password.request'))
            ->assertSee(route('personnel.setup'));
    }

    public function test_an_active_responder_signs_in_with_their_email(): void
    {
        $user = $this->responder();

        foreach (['ana.reyes@gmail.com', 'Ana.Reyes@Gmail.com', 'anareyes+duty@gmail.com'] as $email) {
            $this->post(route('personnel.login.store'), ['email' => $email, 'password' => 'bantay-2026!'])
                ->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs($user);
            $this->post(route('admin.logout'));
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_a_mobile_number_no_longer_signs_in(): void
    {
        $this->responder();

        $this->post(route('personnel.login.store'), ['email' => '09175551234', 'password' => 'bantay-2026!'])
            ->assertSessionHasErrors(['email' => PersonnelLoginController::FAILED_MESSAGE]);
        $this->assertGuest();
    }

    public function test_every_failure_shows_the_same_generic_message(): void
    {
        $this->responder();
        User::factory()->create(['role' => UserRole::Resident, 'email' => 'resident@gmail.com', 'password' => Hash::make('bantay-2026!')]);

        $attempts = [
            ['email' => 'ana.reyes@gmail.com', 'password' => 'wrong-password'],
            ['email' => 'nobody@gmail.com', 'password' => 'bantay-2026!'],
            ['email' => 'not an email', 'password' => 'bantay-2026!'],
            ['email' => 'resident@gmail.com', 'password' => 'bantay-2026!'],
        ];

        foreach ($attempts as $credentials) {
            $this->post(route('personnel.login.store'), $credentials)
                ->assertSessionHasErrors(['email' => PersonnelLoginController::FAILED_MESSAGE]);
        }

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.personnel_login_failed']);
    }

    public function test_an_unclaimed_account_cannot_sign_in_with_a_password(): void
    {
        // The admin-created placeholder hash is never a usable password.
        $this->responder(['account_setup_completed_at' => null, 'email_verified_at' => null, 'password' => Hash::make('guess')]);

        $this->post(route('personnel.login.store'), ['email' => 'ana.reyes@gmail.com', 'password' => 'guess'])
            ->assertSessionHasErrors(['email' => PersonnelLoginController::FAILED_MESSAGE]);
        $this->assertGuest();
        Notification::assertNothingSent();
    }

    public function test_five_failures_lock_the_email_for_fifteen_minutes(): void
    {
        $user = $this->responder();

        foreach (range(1, 5) as $ignored) {
            $this->post(route('personnel.login.store'), ['email' => 'ana.reyes@gmail.com', 'password' => 'wrong-password'])
                ->assertSessionHasErrors('email');
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.personnel_login_locked', 'auditable_id' => $user->id]);

        // Even the right password is refused while locked, under any spelling of the inbox.
        $this->travel(1)->minutes();
        $this->post(route('personnel.login.store'), ['email' => 'AnaReyes@gmail.com', 'password' => 'bantay-2026!'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many failed attempts', session('errors')->first('email'));
        $this->assertGuest();

        $this->travel(15)->minutes();
        $this->post(route('personnel.login.store'), ['email' => 'ana.reyes@gmail.com', 'password' => 'bantay-2026!'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_requests_are_rate_limited(): void
    {
        $this->responder();

        foreach (range(1, 10) as $ignored) {
            $this->post(route('personnel.login.store'), ['email' => 'someone@gmail.com', 'password' => 'x']);
        }

        $this->post(route('personnel.login.store'), ['email' => 'someone@gmail.com', 'password' => 'x'])
            ->assertTooManyRequests();
    }

    public function test_an_account_with_an_unverified_email_must_verify_by_link_before_signing_in(): void
    {
        // Chose a password under the earlier SMS flow but never opened the email link.
        $user = $this->responder(['email_verified_at' => null]);

        $this->post(route('personnel.login.store'), ['email' => 'ana.reyes@gmail.com', 'password' => 'bantay-2026!'])
            ->assertRedirect(route('verification.notice'));

        $this->assertGuest();
        Notification::assertSentTo($user, ConfirmPersonnelEmail::class);

        $link = Notification::sent($user, ConfirmPersonnelEmail::class)->last()->confirmationUrl;
        $this->get($link)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->refresh()->needsAccountSetup());
    }

    public function test_personnel_are_turned_away_from_the_staff_login(): void
    {
        $this->responder();

        $this->post(route('admin.login.store'), ['email' => 'ana.reyes@gmail.com', 'password' => 'bantay-2026!'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->get(route('admin.login'))->assertSee(route('personnel.login'));
    }

    public function test_forgot_password_resets_it_by_emailed_link(): void
    {
        $user = $this->responder();

        $this->get(route('personnel.password.request'))->assertOk()
            ->assertSee('Back to sign in')
            ->assertSee('Send Reset Link')
            ->assertDontSee('Mobile Number');

        $this->post(route('personnel.password.send'), ['email' => 'ana.reyes@gmail.com'])
            ->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPersonnelPassword::class);

        $this->get(route('personnel.password.reset'))->assertRedirect(route('personnel.password.request'));

        $this->get($this->latestResetLink($user))->assertRedirect(route('personnel.password.reset'));
        $this->get(route('personnel.password.reset'))->assertOk()->assertSee('Your reset link is confirmed.');

        $this->post(route('personnel.password.update'), ['password' => '09175551234', 'password_confirmation' => '09175551234'])
            ->assertSessionHasErrors('password');

        $this->post(route('personnel.password.update'), ['password' => 'new-bantay-99', 'password_confirmation' => 'new-bantay-99'])
            ->assertRedirect(route('personnel.login'));

        $this->assertTrue(Hash::check('new-bantay-99', $user->refresh()->password));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_reset', 'auditable_id' => $user->id]);

        $this->post(route('personnel.login.store'), ['email' => 'ana.reyes@gmail.com', 'password' => 'new-bantay-99'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_forgot_password_does_not_reveal_unknown_emails_or_email_them(): void
    {
        $this->responder(['email_verified_at' => null]);

        foreach (['nobody@gmail.com', 'ana.reyes@gmail.com'] as $email) {
            $this->post(route('personnel.password.send'), ['email' => $email])
                ->assertSessionHas('status', 'If this email belongs to a registered responder, we sent it a password reset link. Links can be requested once every 60 seconds.');
        }

        Notification::assertNothingSent();
    }

    public function test_a_reset_link_works_once_and_expires_after_24_hours(): void
    {
        $user = $this->responder();

        $this->post(route('personnel.password.send'), ['email' => 'ana.reyes@gmail.com']);
        $link = $this->latestResetLink($user);
        $this->get($link)->assertRedirect(route('personnel.password.reset'));
        $this->flushSession();

        $this->get($link)->assertRedirect(route('personnel.password.request'))->assertSessionHasErrors('email');

        $this->travel(61)->seconds();
        $this->post(route('personnel.password.send'), ['email' => 'ana.reyes@gmail.com']);
        $expired = $this->latestResetLink($user);
        $this->travel(24)->hours();
        $this->travel(1)->minutes();

        $this->get($expired)->assertRedirect(route('personnel.password.request'))->assertSessionHasErrors('email');
        $this->get(route('personnel.password.reset'))->assertRedirect(route('personnel.password.request'));
    }

    public function test_a_verification_link_token_cannot_reset_a_password(): void
    {
        $user = $this->responder(['account_setup_completed_at' => null, 'email_verified_at' => null]);

        $this->post(route('personnel.setup.send'), ['email' => 'ana.reyes@gmail.com']);
        $token = basename(Notification::sent($user, ConfirmPersonnelEmail::class)->last()->confirmationUrl);

        $this->get(route('personnel.password.link', ['user' => $user->id, 'token' => $token]))
            ->assertRedirect(route('personnel.password.request'))
            ->assertSessionHasErrors('email');
    }

    public function test_the_audit_trail_never_contains_tokens_or_passwords(): void
    {
        $user = $this->responder();

        $this->post(route('personnel.login.store'), ['email' => 'ana.reyes@gmail.com', 'password' => 'wrong-password']);
        $this->post(route('personnel.password.send'), ['email' => 'ana.reyes@gmail.com']);
        $link = $this->latestResetLink($user);
        $this->get($link);
        $this->post(route('personnel.password.update'), ['password' => 'new-bantay-99', 'password_confirmation' => 'new-bantay-99']);
        $this->post(route('personnel.login.store'), ['email' => 'ana.reyes@gmail.com', 'password' => 'new-bantay-99']);

        $trail = json_encode(AuditLog::all()->toArray());

        foreach ([basename($link), 'wrong-password', 'new-bantay-99', 'bantay-2026!', $user->refresh()->password] as $secret) {
            $this->assertStringNotContainsString($secret, $trail);
        }

        foreach (['auth.password_reset_link_sent', 'auth.password_reset', 'auth.personnel_login_failed', 'auth.login'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action]);
        }

        $entry = AuditLog::where('action', 'auth.personnel_login_failed')->first();
        $this->assertSame($user->id, $entry->auditable_id);
        $this->assertNotNull($entry->ip_address);
        $this->assertNotNull($entry->created_at);
    }
}
