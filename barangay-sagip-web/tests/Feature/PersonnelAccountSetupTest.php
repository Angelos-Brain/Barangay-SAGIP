<?php

namespace Tests\Feature;

use App\Enums\PersonnelAccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\ConfirmPersonnelEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * First Login, steps 3–5: after verifying their mobile number a responder
 * chooses a password and confirms their email by link; only then does the
 * account become active and reach the dashboard.
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

    private function phoneVerifiedResponder(): User
    {
        return User::factory()->needsAccountSetup()->create([
            'role' => UserRole::Medical,
            'email' => 'ana.reyes@example.com',
            'phone_number' => '09175551234',
        ]);
    }

    private function confirmationUrl(User $user): string
    {
        $url = null;

        Notification::assertSentTo($user, ConfirmPersonnelEmail::class, function (ConfirmPersonnelEmail $notification) use (&$url) {
            $url = $notification->confirmationUrl;

            return true;
        });

        return $url;
    }

    public function test_a_responder_is_kept_on_the_setup_steps_until_the_account_is_active(): void
    {
        $responder = $this->phoneVerifiedResponder();

        $this->actingAs($responder)->get(route('dashboard'))->assertRedirect(route('account.setup.password'));
        $this->actingAs($responder)->get(route('requests.index'))->assertRedirect(route('account.setup.password'));
        $this->actingAs($responder)->get(route('account.setup.password'))
            ->assertOk()->assertSee('Choose a password')->assertSee('Back (sign out)');
    }

    public function test_an_unclaimed_account_that_is_somehow_signed_in_is_sent_back_to_first_login(): void
    {
        $responder = User::factory()->unclaimed()->create(['role' => UserRole::Medical]);

        $this->actingAs($responder)->get(route('dashboard'))->assertRedirect(route('personnel.setup'));
        $this->assertGuest();
    }

    public function test_setting_a_password_hashes_it_and_moves_on_to_the_email_step(): void
    {
        $responder = $this->phoneVerifiedResponder();

        $this->actingAs($responder)->post(route('account.setup.password.store'), [
            'password' => 'bantay-2026!',
            'password_confirmation' => 'bantay-2026!',
        ])->assertRedirect(route('account.setup.email'));

        $responder->refresh();
        $this->assertNotSame('bantay-2026!', $responder->password);
        $this->assertTrue(Hash::check('bantay-2026!', $responder->password));
        $this->assertTrue($responder->hasChosenPassword());
        $this->assertSame(PersonnelAccountStatus::PhoneVerified, $responder->personnelAccountStatus());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_set', 'auditable_id' => $responder->id]);

        // Still not active: the email step is next, not the dashboard.
        $this->actingAs($responder)->get(route('dashboard'))->assertRedirect(route('account.setup.email'));
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $responder = $this->phoneVerifiedResponder();

        foreach (['short1', '09175551234', '+63 917 555 1234', 'ANA.REYES@example.com'] as $weak) {
            $this->actingAs($responder)->post(route('account.setup.password.store'), [
                'password' => $weak,
                'password_confirmation' => $weak,
            ])->assertSessionHasErrors('password');
        }

        $this->actingAs($responder)->post(route('account.setup.password.store'), [
            'password' => 'bantay-2026!',
            'password_confirmation' => 'something-else',
        ])->assertSessionHasErrors('password');

        $this->assertFalse($responder->refresh()->hasChosenPassword());
    }

    public function test_the_email_step_prefills_the_admin_email_and_sends_a_link(): void
    {
        $responder = User::factory()->needsAccountSetup()->create([
            'role' => UserRole::Medical, 'email' => 'ana.reyes@example.com', 'account_setup_completed_at' => now(),
        ]);

        $this->actingAs($responder)->get(route('account.setup.email'))
            ->assertOk()->assertSee('ana.reyes@example.com')->assertSee('Back to password');

        $this->actingAs($responder)->post(route('account.setup.email.store'), ['email' => 'ana.reyes@example.com'])
            ->assertRedirect(route('account.setup.email'));

        Notification::assertSentTo($responder, ConfirmPersonnelEmail::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.email_confirmation_sent', 'auditable_id' => $responder->id]);
        $this->assertNull($responder->refresh()->email_verified_at);
    }

    public function test_the_email_must_pass_the_existing_email_validation(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $responder = User::factory()->needsAccountSetup()->create([
            'role' => UserRole::Medical, 'account_setup_completed_at' => now(),
        ]);

        foreach (['not-an-email', 'someone@localhost', 'someone@mailinator.com', 'taken@example.com'] as $invalid) {
            $this->actingAs($responder)->post(route('account.setup.email.store'), ['email' => $invalid])
                ->assertSessionHasErrors('email');
            $this->travel(21)->seconds(); // stay under the 3-per-minute send throttle
        }

        Notification::assertNothingSent();
    }

    public function test_the_confirmation_link_activates_the_account_once(): void
    {
        $responder = User::factory()->needsAccountSetup()->create([
            'role' => UserRole::Medical, 'email' => 'ana.reyes@example.com', 'account_setup_completed_at' => now(),
        ]);
        $this->actingAs($responder)->post(route('account.setup.email.store'), ['email' => 'ana.reyes@example.com']);
        $url = $this->confirmationUrl($responder);

        $this->actingAs($responder)->get($url)->assertRedirect(route('account.setup.ready'));

        $this->assertSame(PersonnelAccountStatus::Active, $responder->refresh()->personnelAccountStatus());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.email_confirmed', 'auditable_id' => $responder->id]);
        $this->actingAs($responder)->get(route('account.setup.ready'))->assertOk()->assertSee('Your account is ready');
        $this->actingAs($responder)->get(route('dashboard'))->assertOk();

        // Single use: the same link does nothing for a second account state.
        $responder->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($responder)->get($url)->assertRedirect(route('account.setup.email'))->assertSessionHasErrors('email');
    }

    public function test_the_link_works_when_opened_signed_out_on_another_device(): void
    {
        $responder = User::factory()->needsAccountSetup()->create([
            'role' => UserRole::Medical, 'account_setup_completed_at' => now(),
        ]);
        $this->actingAs($responder)->post(route('account.setup.email.store'), ['email' => 'ana.reyes@example.com']);
        $url = $this->confirmationUrl($responder);
        auth()->logout();

        $this->get($url)->assertOk()->assertSee('Your account is ready')->assertSee('Sign In');
        $this->assertSame(PersonnelAccountStatus::Active, $responder->refresh()->personnelAccountStatus());
    }

    public function test_the_confirmation_link_expires_after_24_hours(): void
    {
        $responder = User::factory()->needsAccountSetup()->create([
            'role' => UserRole::Medical, 'account_setup_completed_at' => now(),
        ]);
        $this->actingAs($responder)->post(route('account.setup.email.store'), ['email' => 'ana.reyes@example.com']);
        $url = $this->confirmationUrl($responder);

        $this->travel(25)->hours();

        $this->actingAs($responder)->get($url)->assertSessionHasErrors('email');
        $this->assertNull($responder->refresh()->email_verified_at);
    }

    public function test_changing_the_email_invalidates_the_earlier_link(): void
    {
        $responder = User::factory()->needsAccountSetup()->create([
            'role' => UserRole::Medical, 'account_setup_completed_at' => now(),
        ]);
        $this->actingAs($responder)->post(route('account.setup.email.store'), ['email' => 'first@example.com']);
        $firstUrl = $this->confirmationUrl($responder);

        $this->actingAs($responder)->post(route('account.setup.email.store'), ['email' => 'second@example.com']);

        $this->actingAs($responder)->get($firstUrl)->assertSessionHasErrors('email');
        $this->assertNull($responder->refresh()->email_verified_at);
        $this->assertSame('second@example.com', $responder->email);
    }

    public function test_resending_the_link_has_a_cooldown(): void
    {
        $responder = User::factory()->needsAccountSetup()->create([
            'role' => UserRole::Medical, 'account_setup_completed_at' => now(),
        ]);
        $this->actingAs($responder)->post(route('account.setup.email.store'), ['email' => 'ana.reyes@example.com']);

        $this->actingAs($responder)->post(route('account.setup.email.resend'))->assertSessionHasErrors('email');
        Notification::assertSentToTimes($responder, ConfirmPersonnelEmail::class, 1);

        $this->travel(61)->seconds();
        $this->actingAs($responder)->post(route('account.setup.email.resend'))->assertSessionHas('status');
        Notification::assertSentToTimes($responder, ConfirmPersonnelEmail::class, 2);
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
