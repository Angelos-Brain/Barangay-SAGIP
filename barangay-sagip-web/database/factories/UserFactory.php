<?php

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => now(),
            'account_setup_completed_at' => now(),
            'phone_verified_at' => now(),
        ];
    }

    /**
     * Feature 1: an account still waiting for an official to review it.
     */
    public function pendingVerification(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => VerificationStatus::Pending,
            'verified_at' => null,
        ]);
    }

    /**
     * Feature 1: an account an official turned down.
     */
    public function rejectedVerification(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => VerificationStatus::Rejected,
            'verified_at' => null,
        ]);
    }

    /**
     * A responder who verified their mobile number but has not yet chosen a
     * password or confirmed their email.
     */
    public function needsAccountSetup(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_setup_completed_at' => null,
            'email_verified_at' => null,
        ]);
    }

    /**
     * A responder record an official added that nobody has claimed yet.
     */
    public function unclaimed(): static
    {
        return $this->needsAccountSetup()->state(fn (array $attributes) => [
            'phone_verified_at' => null,
        ]);
    }

    /**
     * A responder who chose a password but has not clicked the email link.
     */
    public function awaitingEmailConfirmation(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
