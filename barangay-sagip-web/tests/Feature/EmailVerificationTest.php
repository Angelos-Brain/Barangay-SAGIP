<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\VerifyResidentEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A new resident's account stays unverified, and cannot sign in, until the
 * single-use link emailed to their Gmail address is opened.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
    }

    /**
     * @return array<string, string>
     */
    private function registration(string $email = 'juan.delacruz@gmail.com'): array
    {
        return [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => $email,
            'phone_number' => '09171234567',
            'address' => '225, Provincial Road, Calatagan Tibang, Virac, Catanduanes',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];
    }

    private function register(string $email = 'juan.delacruz@gmail.com'): User
    {
        $this->post(route('register'), $this->registration($email))->assertRedirect(route('verification.notice'));

        return User::where('email', $email)->sole();
    }

    private function latestLink(User $user): string
    {
        return Notification::sent($user, VerifyResidentEmail::class)->last()->verificationUrl;
    }

    public function test_registration_creates_an_unverified_account_and_emails_a_link_instead_of_signing_in(): void
    {
        $user = $this->register();

        $this->assertGuest();
        $this->assertNull($user->email_verified_at);
        $this->assertSame('09171234567', $user->phone_number);
        Notification::assertSentTo($user, VerifyResidentEmail::class);

        $this->get(route('verification.notice'))->assertOk()
            ->assertSee('Check your Gmail to verify your account')
            ->assertSee('juan.delacruz@gmail.com')
            ->assertSee('Resend verification email');
    }

    public function test_the_email_goes_to_the_address_as_typed(): void
    {
        $user = $this->register('juan.dela.cruz+sagip@gmail.com');

        Notification::assertSentTo($user, VerifyResidentEmail::class, function ($notification, array $channels, User $notifiable) {
            return $notifiable->routeNotificationFor('mail') === 'juan.dela.cruz+sagip@gmail.com';
        });
    }

    public function test_an_unverified_resident_cannot_sign_in_or_reach_any_page(): void
    {
        $this->register();

        $this->post(route('login'), ['email' => 'juan.delacruz@gmail.com', 'password' => 'Password123!'])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', 'Check your Gmail to verify your account before signing in.');

        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('requests.create'))->assertRedirect(route('login'));
    }

    public function test_opening_the_link_verifies_the_account_so_the_resident_can_sign_in(): void
    {
        $user = $this->register();

        $this->get($this->latestLink($user))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your email is verified. You can now sign in.');

        $this->assertNotNull($user->refresh()->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.email_verified', 'auditable_id' => $user->id]);

        // Any spelling of the same Gmail inbox signs in.
        $this->post(route('login'), ['email' => 'JuanDelaCruz@Gmail.com', 'password' => 'Password123!'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_used_link_cannot_be_used_again(): void
    {
        $user = $this->register();
        $link = $this->latestLink($user);

        $this->get($link)->assertRedirect(route('login'));
        $this->get($link)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'This link was already used and your email is verified. You can sign in.');
    }

    public function test_an_expired_link_shows_a_clear_error_with_a_way_to_resend(): void
    {
        $user = $this->register();
        $link = $this->latestLink($user);
        $this->flushSession();

        $this->travel(24)->hours();
        $this->travel(1)->minutes();

        $this->get($link)
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasErrors(['link' => 'This verification link has expired or was already used. Request a new one below.']);
        $this->assertNull($user->refresh()->email_verified_at);

        $this->followingRedirects()->get($link)->assertOk()
            ->assertSee('This verification link has expired or was already used.')
            ->assertSee('Resend verification email');

        $this->post(route('verification.send'))->assertSessionHas('status');
        Notification::assertSentToTimes($user, VerifyResidentEmail::class, 2);

        $this->get($this->latestLink($user))->assertRedirect(route('login'));
        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_a_tampered_or_another_accounts_token_is_rejected(): void
    {
        $user = $this->register();
        $other = $this->register('maria.santos@gmail.com');
        $token = basename($this->latestLink($user));

        $this->get(route('verification.verify', ['user' => $user->id, 'token' => str_repeat('a', 64)]))
            ->assertSessionHasErrors('link');
        $this->get(route('verification.verify', ['user' => $other->id, 'token' => $token]))
            ->assertSessionHasErrors('link');

        $this->assertNull($user->refresh()->email_verified_at);
        $this->assertNull($other->refresh()->email_verified_at);
    }

    public function test_resending_has_a_60_second_cooldown_and_replaces_the_old_link(): void
    {
        $user = $this->register();
        $firstLink = $this->latestLink($user);

        $this->post(route('verification.send'))->assertSessionHasErrors('link');
        Notification::assertSentToTimes($user, VerifyResidentEmail::class, 1);
        $this->get(route('verification.notice'))->assertSee('data-wait="60"', false);

        $this->travel(61)->seconds();
        $this->post(route('verification.send'))
            ->assertSessionHas('status', 'A new verification link was sent to juan.delacruz@gmail.com.');
        Notification::assertSentToTimes($user, VerifyResidentEmail::class, 2);

        $this->get($firstLink)->assertSessionHasErrors('link');
        $this->get($this->latestLink($user))->assertRedirect(route('login'));
    }

    public function test_only_a_hash_of_the_token_is_stored(): void
    {
        $user = $this->register();
        $token = basename($this->latestLink($user));

        $pending = Cache::get('sagip:email-link:verify:'.$user->id);

        $this->assertSame(hash('sha256', $token), $pending['hash']);
        $this->assertStringNotContainsString($token, serialize($pending));
        $this->assertStringNotContainsString($token, json_encode(AuditLog::all()->toArray()));
    }

    public function test_existing_verified_residents_sign_in_as_before(): void
    {
        $resident = User::factory()->create([
            'role' => UserRole::Resident,
            'email' => 'old.resident@yahoo.com',
            'password' => Hash::make('Password123!'),
        ]);

        $this->post(route('login'), ['email' => 'old.resident@yahoo.com', 'password' => 'Password123!'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($resident);
    }
}
