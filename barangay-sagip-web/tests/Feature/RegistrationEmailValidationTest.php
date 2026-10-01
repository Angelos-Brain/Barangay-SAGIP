<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 6: Email Validation.
 */
class RegistrationEmailValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(string $email): array
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

    private function assertEmailRejected(string $email): void
    {
        $this->from(route('register'))
            ->post(route('register'), $this->payload($email))
            ->assertSessionHasErrors('email')
            ->assertRedirect(route('register'));

        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertGuest();
    }

    public function test_a_well_formed_address_is_accepted(): void
    {
        $this->post(route('register'), $this->payload('juan.delacruz@gmail.com'))
            ->assertRedirect(route('residents.profile.edit'));

        $this->assertDatabaseHas('users', ['email' => 'juan.delacruz@gmail.com']);
    }

    public function test_plus_addressing_is_still_accepted(): void
    {
        $this->post(route('register'), $this->payload('juan+sagip@gmail.com'))
            ->assertRedirect(route('residents.profile.edit'));

        $this->assertDatabaseHas('users', ['email' => 'juan+sagip@gmail.com']);
    }

    public function test_malformed_addresses_are_rejected(): void
    {
        foreach ([
            'not-an-email',
            'juan@',
            '@gmail.com',
            'juan@@gmail.com',
            'juan..delacruz@gmail.com',
            '.juan@gmail.com',
            'juan.@gmail.com',
            'juan@gmail..com',
            'juan@-gmail.com',
            'juan@gmail-.com',
            'juan dela cruz@gmail.com',
        ] as $email) {
            $this->assertEmailRejected($email);
        }
    }

    /**
     * Surrounding whitespace never reaches the rules — Laravel's TrimStrings
     * middleware removes it first — so the trimmed address is what gets stored.
     */
    public function test_surrounding_whitespace_is_trimmed_rather_than_rejected(): void
    {
        $this->post(route('register'), $this->payload('  juan.delacruz@gmail.com  '))
            ->assertRedirect(route('residents.profile.edit'));

        $this->assertDatabaseHas('users', ['email' => 'juan.delacruz@gmail.com']);
    }

    public function test_addresses_without_a_public_tld_are_rejected(): void
    {
        foreach (['juan@localhost', 'juan@gmail', 'juan@192.168.1.10', 'juan@gmail.c', 'juan@gmail.123'] as $email) {
            $this->assertEmailRejected($email);
        }
    }

    public function test_disposable_mailbox_providers_are_rejected(): void
    {
        foreach (['juan@mailinator.com', 'juan@throwaway.mailinator.com', 'juan@yopmail.com', 'juan@10minutemail.com'] as $email) {
            $this->assertEmailRejected($email);
        }
    }

    public function test_the_rejection_message_explains_why_a_disposable_address_failed(): void
    {
        $this->from(route('register'))
            ->post(route('register'), $this->payload('juan@mailinator.com'))
            ->assertSessionHasErrors([
                'email' => 'Temporary or disposable email addresses are not accepted. Use a personal or work email address.',
            ]);
    }

    public function test_a_duplicate_address_is_still_rejected(): void
    {
        $this->post(route('register'), $this->payload('juan.delacruz@gmail.com'));
        $this->post(route('logout'));

        $this->from(route('register'))
            ->post(route('register'), $this->payload('juan.delacruz@gmail.com'))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'juan.delacruz@gmail.com')->count());
    }
}
