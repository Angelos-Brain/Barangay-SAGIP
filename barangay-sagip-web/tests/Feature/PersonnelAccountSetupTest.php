<?php

namespace Tests\Feature;

use App\Enums\PersonnelAccountStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ResponsePersonnel;
use App\Models\User;
use App\Notifications\ConfirmPersonnelEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * First Login: a responder an official added opens the link emailed to their
 * Gmail address, which verifies it and signs them in, then chooses a
 * password; only then is the account active and able to reach the dashboard.
 */
class PersonnelAccountSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
    }

    private function responder(string $state = 'unclaimed', string $email = 'ana.reyes@gmail.com'): User
    {
        $factory = User::factory();
        $user = ($state === 'unclaimed' ? $factory->unclaimed() : $factory->needsAccountSetup())->create([
            'role' => UserRole::Medical,
            'email' => $email,
            'phone_number' => '09175551234',
        ]);

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

    private function latestLink(User $user): string
    {
        return Notification::sent($user, ConfirmPersonnelEmail::class)->last()->confirmationUrl;
    }

    private function choosePassword(): void
    {
        $this->post(route('account.setup.password.store'), [
            'password' => 'bantay-2026!',
            'password_confirmation' => 'bantay-2026!',
        ])->assertRedirect(route('account.setup.ready'));
    }

    public function test_first_login_asks_for_the_email_and_never_a_mobile_number_or_code(): void
    {
        $this->get(route('personnel.setup'))->assertOk()
            ->assertSee('Send Verification Link')
            ->assertSee('Gmail address your barangay admin registered')
            ->assertDontSee('Mobile Number')
            ->assertDontSee('code');
    }

    public function test_requesting_a_link_emails_it_and_shows_check_your_gmail(): void
    {
        $user = $this->responder();

        // Matched on the inbox, so a different spelling of the address works.
        $this->post(route('personnel.setup.send'), ['email' => 'AnaReyes@gmail.com'])
            ->assertRedirect(route('verification.notice'));

        $this->assertGuest();
        Notification::assertSentTo($user, ConfirmPersonnelEmail::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.email_verification_sent', 'auditable_id' => $user->id]);

        $this->get(route('verification.notice'))->assertOk()
            ->assertSee('Check your Gmail to verify your account')
            ->assertSee('ana.reyes@gmail.com')
            ->assertSee('Resend verification email');
    }

    public function test_emails_that_are_not_on_the_personnel_roster_are_refused(): void
    {
        User::factory()->create(['role' => UserRole::Resident, 'email' => 'resident@gmail.com']);
        User::factory()->create(['role' => UserRole::Official, 'email' => 'official@gmail.com']);
        $removed = $this->responder('unclaimed', 'removed@gmail.com');
        $removed->responsePersonnel->delete();

        foreach (['nobody@gmail.com', 'resident@gmail.com', 'official@gmail.com', 'removed@gmail.com'] as $email) {
            $this->post(route('personnel.setup.send'), ['email' => $email])
                ->assertSessionHasErrors(['email' => 'This email is not registered as barangay personnel. Contact your barangay admin.']);
            $this->travel(21)->seconds(); // stay under the 3-per-minute send throttle
        }

        Notification::assertNothingSent();
    }

    public function test_an_active_account_is_told_to_sign_in_and_gets_no_email(): void
    {
        $this->responder()->forceFill(['email_verified_at' => now(), 'account_setup_completed_at' => now()])->save();

        $this->post(route('personnel.setup.send'), ['email' => 'ana.reyes@gmail.com'])
            ->assertSessionHasErrors(['email' => 'This account is already set up. Sign in with your email and password.']);

        Notification::assertNothingSent();
    }

    public function test_the_link_verifies_the_email_and_signs_in_to_the_password_step(): void
    {
        $user = $this->responder();
        $this->post(route('personnel.setup.send'), ['email' => 'ana.reyes@gmail.com']);

        $this->get($this->latestLink($user))->assertRedirect(route('account.setup.password'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(PersonnelAccountStatus::EmailVerified, $user->refresh()->personnelAccountStatus());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.email_verified', 'auditable_id' => $user->id]);

        // Kept on the setup steps until a password is chosen.
        $this->get(route('dashboard'))->assertRedirect(route('account.setup.password'));
        $this->get(route('requests.index'))->assertRedirect(route('account.setup.password'));
        $this->get(route('account.setup.password'))->assertOk()
            ->assertSee('Choose a password')
            ->assertSee('Your email is verified')
            ->assertSee('Back (sign out)');
    }

    public function test_choosing_a_password_activates_the_account(): void
    {
        $user = $this->responder();
        $this->post(route('personnel.setup.send'), ['email' => 'ana.reyes@gmail.com']);
        $this->get($this->latestLink($user));

        $this->choosePassword();

        $user->refresh();
        $this->assertNotSame('bantay-2026!', $user->password);
        $this->assertTrue(Hash::check('bantay-2026!', $user->password));
        $this->assertSame(PersonnelAccountStatus::Active, $user->personnelAccountStatus());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_set', 'auditable_id' => $user->id]);

        $this->get(route('account.setup.ready'))->assertOk()
            ->assertSee('Your account is ready')
            ->assertSee('sign in with your email and password');
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_the_link_opened_signed_out_on_another_device_signs_in_there(): void
    {
        $user = $this->responder();
        $this->post(route('personnel.setup.send'), ['email' => 'ana.reyes@gmail.com']);
        $link = $this->latestLink($user);
        $this->flushSession();

        $this->get($link)->assertRedirect(route('account.setup.password'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $user = $this->responder('email_verified');

        foreach (['short1', '09175551234', '+63 917 555 1234', 'ANA.REYES@gmail.com'] as $weak) {
            $this->actingAs($user)->post(route('account.setup.password.store'), [
                'password' => $weak,
                'password_confirmation' => $weak,
            ])->assertSessionHasErrors('password');
        }

        $this->actingAs($user)->post(route('account.setup.password.store'), [
            'password' => 'bantay-2026!',
            'password_confirmation' => 'something-else',
        ])->assertSessionHasErrors('password');

        $this->assertFalse($user->refresh()->hasChosenPassword());
    }

    public function test_a_used_link_cannot_be_used_again(): void
    {
        $user = $this->responder();
        $this->post(route('personnel.setup.send'), ['email' => 'ana.reyes@gmail.com']);
        $link = $this->latestLink($user);
        $this->get($link);
        $this->choosePassword();
        $this->post(route('logout'));

        $this->get($link)
            ->assertRedirect(route('personnel.login'))
            ->assertSessionHas('status', 'This link was already used and your email is verified. You can sign in.');
        $this->assertGuest();
    }

    public function test_an_expired_link_shows_a_clear_error_with_a_way_to_resend(): void
    {
        $user = $this->responder();
        $this->post(route('personnel.setup.send'), ['email' => 'ana.reyes@gmail.com']);
        $link = $this->latestLink($user);

        $this->travel(24)->hours();
        $this->travel(1)->minutes();

        $this->get($link)
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasErrors('link');
        $this->assertGuest();
        $this->assertSame(PersonnelAccountStatus::Unclaimed, $user->refresh()->personnelAccountStatus());

        $this->followingRedirects()->get($link)
            ->assertSee('This verification link has expired or was already used.')
            ->assertSee('Resend verification email');

        $this->post(route('verification.send'))->assertSessionHas('status');
        $this->get($this->latestLink($user))->assertRedirect(route('account.setup.password'));
    }

    public function test_resending_has_a_60_second_cooldown(): void
    {
        $user = $this->responder();
        $this->post(route('personnel.setup.send'), ['email' => 'ana.reyes@gmail.com']);
        $firstLink = $this->latestLink($user);

        $this->post(route('verification.send'))->assertSessionHasErrors('link');
        $this->post(route('personnel.setup.send'), ['email' => 'ana.reyes@gmail.com']);
        Notification::assertSentToTimes($user, ConfirmPersonnelEmail::class, 1);

        $this->travel(61)->seconds();
        $this->post(route('verification.send'))->assertSessionHas('status');
        Notification::assertSentToTimes($user, ConfirmPersonnelEmail::class, 2);

        // The new link replaces the old one.
        $this->get($firstLink)->assertSessionHasErrors('link');
    }

    public function test_only_a_hash_of_the_token_is_stored(): void
    {
        $user = $this->responder();
        $this->post(route('personnel.setup.send'), ['email' => 'ana.reyes@gmail.com']);
        $token = basename($this->latestLink($user));

        $pending = Cache::get('sagip:email-link:verify:'.$user->id);

        $this->assertSame(hash('sha256', $token), $pending['hash']);
        $this->assertStringNotContainsString($token, serialize($pending));
        $this->assertStringNotContainsString($token, json_encode(AuditLog::all()->toArray()));
    }

    public function test_a_responder_who_left_before_choosing_a_password_can_get_back_in_by_link(): void
    {
        $user = $this->responder('email_verified');

        $this->post(route('personnel.setup.send'), ['email' => 'ana.reyes@gmail.com'])
            ->assertRedirect(route('verification.notice'));

        $this->get($this->latestLink($user))->assertRedirect(route('account.setup.password'));
        $this->choosePassword();
        $this->assertSame(PersonnelAccountStatus::Active, $user->refresh()->personnelAccountStatus());
    }

    public function test_an_unclaimed_account_that_is_somehow_signed_in_is_sent_back_to_first_login(): void
    {
        $user = $this->responder();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('personnel.setup'));
        $this->assertGuest();
    }

    public function test_an_active_responder_goes_straight_to_the_dashboard(): void
    {
        $responder = User::factory()->create(['role' => UserRole::Medical]);

        $this->actingAs($responder)->get(route('dashboard'))->assertOk();
        $this->actingAs($responder)->get(route('account.setup'))->assertRedirect(route('dashboard'));
        $this->actingAs($responder)->get(route('account.setup.password'))->assertRedirect(route('dashboard'));
    }

    public function test_residents_and_officials_are_never_sent_to_personnel_setup(): void
    {
        $resident = User::factory()->needsAccountSetup()->create(['role' => UserRole::Resident]);
        $official = User::factory()->unclaimed()->create(['role' => UserRole::Official]);

        $this->assertFalse($resident->needsAccountSetup());
        $this->assertFalse($official->needsAccountSetup());
        $this->actingAs($official)->get(route('dashboard'))->assertOk();
    }
}
