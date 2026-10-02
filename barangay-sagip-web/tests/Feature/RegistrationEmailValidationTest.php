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
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', ['email' => 'juan.delacruz@gmail.com']);
    }

    public function test_plus_addressing_is_still_accepted(): void
    {
        $this->post(route('register'), $this->payload('juan+sagip@gmail.com'))
            ->assertRedirect(route('verification.notice'));

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
            ->assertRedirect(route('verification.notice'));

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

        $this->from(route('register'))
            ->post(route('register'), $this->payload('juan.delacruz@gmail.com'))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'juan.delacruz@gmail.com')->count());
    }

    public function test_non_gmail_addresses_are_rejected_with_a_clear_message(): void
    {
        foreach (['juan@yahoo.com', 'juan@outlook.com', 'juan@gmail.com.ph', 'juan@googlemail.com', 'juan@mail.gmail.com'] as $email) {
            $this->from(route('register'))
                ->post(route('register'), $this->payload($email))
                ->assertSessionHasErrors(['email' => 'Please use a Gmail address (example@gmail.com).']);

            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
    }

    public function test_uppercase_input_is_lowercased_rather_than_rejected(): void
    {
        $this->post(route('register'), $this->payload('Juan.DelaCruz@Gmail.com'))
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', ['email' => 'juan.delacruz@gmail.com', 'email_canonical' => 'juandelacruz@gmail.com']);
    }

    public function test_dots_and_plus_tags_cannot_register_the_same_inbox_twice(): void
    {
        $this->post(route('register'), $this->payload('john.doe+x@gmail.com'))
            ->assertRedirect(route('verification.notice'));

        foreach (['johndoe@gmail.com', 'j.o.h.n.d.o.e@gmail.com', 'johndoe+sagip@gmail.com'] as $email) {
            $this->from(route('register'))
                ->post(route('register'), $this->payload($email))
                ->assertSessionHasErrors('email');
        }

        $this->assertSame(1, User::where('email_canonical', 'johndoe@gmail.com')->count());
        // Stored as typed, so mail goes to the address the resident entered.
        $this->assertDatabaseHas('users', ['email' => 'john.doe+x@gmail.com']);
    }
}
