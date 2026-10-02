<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Enums\PersonnelAccountStatus;
use App\Enums\UserRole;
use App\Models\ResponsePersonnel;
use App\Models\User;
use App\Notifications\ConfirmPersonnelEmail;
use App\Rules\GmailAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Officials add a responder with a Gmail address and a mobile number. The
 * mobile number is kept as a normal profile field: it is never verified, and
 * no SMS is sent anywhere in setting up or signing in to the account.
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function personnelForm(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana Reyes',
            'email' => 'ana.reyes@gmail.com',
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

        return User::where('email', $overrides['email'] ?? 'ana.reyes@gmail.com')->sole();
    }

    public function test_adding_personnel_creates_an_unclaimed_account_and_emails_the_setup_link(): void
    {
        $user = $this->addPersonnel();

        $this->assertSame('09175551234', $user->phone_number);
        $this->assertSame(UserRole::Medical, $user->role);
        $this->assertSame(PersonnelAccountStatus::Unclaimed, $user->personnelAccountStatus());
        $this->assertSame($user->id, ResponsePersonnel::sole()->user_id);

        Notification::assertSentTo($user, ConfirmPersonnelEmail::class);
        $this->assertSame([], $this->sentSms);
    }

    public function test_the_official_is_told_where_the_link_was_sent(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Official]))
            ->post(route('personnel.store'), $this->personnelForm())
            ->assertSessionHas('status', 'Ana Reyes added. A setup link was emailed to ana.reyes@gmail.com.');
    }

    public function test_the_create_form_asks_for_a_gmail_address(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Official]))
            ->get(route('personnel.create'))
            ->assertOk()
            ->assertSee('Gmail Address')
            ->assertSee('data-gmail-only', false);
    }

    public function test_the_role_follows_the_specialization_tags(): void
    {
        $this->assertSame(UserRole::FireDisaster, $this->addPersonnel([
            'email' => 'fire@gmail.com', 'phone_number' => '09175550001', 'specializations' => ['fire', 'disaster'],
        ])->role);

        $this->assertSame(UserRole::Personnel, $this->addPersonnel([
            'email' => 'multi@gmail.com', 'phone_number' => '09175550002', 'specializations' => ['medical', 'fire'],
        ])->role);
    }

    public function test_adding_personnel_requires_a_valid_unused_mobile_number_and_gmail_address(): void
    {
        User::factory()->create(['phone_number' => '09175551234']);
        User::factory()->create(['email' => 'taken.inbox@gmail.com']);
        $official = User::factory()->create(['role' => UserRole::Official]);

        $this->actingAs($official)
            ->post(route('personnel.store'), $this->personnelForm(['phone_number' => '0917 555 1234']))
            ->assertSessionHasErrors('phone_number');

        $this->actingAs($official)
            ->post(route('personnel.store'), $this->personnelForm(['phone_number' => '12345', 'email' => '']))
            ->assertSessionHasErrors(['phone_number', 'email']);

        $this->actingAs($official)
            ->post(route('personnel.store'), $this->personnelForm(['phone_number' => '09175550009', 'email' => 'ana.reyes@yahoo.com']))
            ->assertSessionHasErrors(['email' => GmailAddress::MESSAGE]);

        $this->actingAs($official)
            ->post(route('personnel.store'), $this->personnelForm(['phone_number' => '09175550009', 'email' => 'takeninbox+x@gmail.com']))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('response_personnel', 0);
        Notification::assertNothingSent();
    }

    public function test_first_login_completes_without_any_sms(): void
    {
        $user = $this->addPersonnel();

        $this->get(Notification::sent($user, ConfirmPersonnelEmail::class)->last()->confirmationUrl)
            ->assertRedirect(route('account.setup.password'));
        $this->post(route('account.setup.password.store'), [
            'password' => 'bantay-2026!',
            'password_confirmation' => 'bantay-2026!',
        ])->assertRedirect(route('account.setup.ready'));
        $this->post(route('admin.logout'));

        $this->post(route('personnel.login.store'), ['email' => 'ana.reyes@gmail.com', 'password' => 'bantay-2026!'])
            ->assertRedirect(route('dashboard'));

        $this->assertSame(PersonnelAccountStatus::Active, $user->refresh()->personnelAccountStatus());
        $this->assertNull($user->phone_verified_at);
        $this->assertSame([], $this->sentSms);
    }

    public function test_changing_the_mobile_number_updates_the_stored_number(): void
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
