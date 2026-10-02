<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\EmergencyRequest;
use App\Models\ResponseAssignment;
use App\Models\ResponsePersonnel;
use App\Models\User;
use App\Notifications\AssignmentReleased;
use App\Notifications\RequestStatusUpdated;
use App\Notifications\ResidentAwaitingVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Feature 10: every activity a user must hear about lands in their in-app
 * notification list.
 */
class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_resident_is_notified_when_their_account_is_verified_even_without_a_queue_worker(): void
    {
        // The real app queues notifications on the database driver; the in-app
        // copy must not wait for a worker to process that queue.
        config(['queue.default' => 'database']);

        $official = User::factory()->create(['role' => UserRole::Official]);
        $resident = User::factory()->pendingVerification()->create(['role' => UserRole::Resident]);

        $this->actingAs($official)
            ->patch(route('verifications.update', $resident), ['decision' => 'verified']);

        $notification = $resident->notifications()->sole();

        $this->assertSame('verified', $notification->data['verification_status']);
        $this->assertStringContainsString('has been verified', $notification->data['message']);
    }

    public function test_a_resident_is_notified_when_their_account_is_rejected_with_the_officials_note(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);
        $resident = User::factory()->pendingVerification()->create(['role' => UserRole::Resident]);

        $this->actingAs($official)
            ->patch(route('verifications.update', $resident), [
                'decision' => 'rejected',
                'note' => 'ID photo was unreadable.',
            ]);

        $message = $resident->notifications()->sole()->data['message'];

        $this->assertStringContainsString('was not approved', $message);
        $this->assertStringContainsString('ID photo was unreadable.', $message);
    }

    public function test_officials_and_admins_are_notified_of_a_new_resident_awaiting_verification(): void
    {
        Notification::fake();

        $official = User::factory()->create(['role' => UserRole::Official]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $otherResident = User::factory()->create(['role' => UserRole::Resident]);

        $this->post(route('register'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@gmail.com',
            'phone_number' => '09171234567',
            'address' => '225, Provincial Road, Calatagan Tibang, Virac, Catanduanes',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        Notification::assertSentTo([$official, $admin], ResidentAwaitingVerification::class);
        Notification::assertNotSentTo($otherResident, ResidentAwaitingVerification::class);
    }

    public function test_reassignment_notifies_the_replaced_responder_and_the_resident(): void
    {
        Notification::fake();

        $official = User::factory()->create(['role' => UserRole::Official]);
        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $request = $this->createRequest($resident, RequestStatus::Assigned);
        $previous = $this->createPersonnel(['current_workload' => 1]);
        $replacement = $this->createPersonnel();

        ResponseAssignment::create([
            'emergency_request_id' => $request->id,
            'response_personnel_id' => $previous->id,
            'was_manual_override' => false,
        ]);

        $this->actingAs($official)
            ->post(route('requests.assign.store', $request), ['response_personnel_id' => $replacement->id]);

        Notification::assertSentTo($previous->user, AssignmentReleased::class);
        Notification::assertNotSentTo($replacement->user, AssignmentReleased::class);
        Notification::assertSentTo($resident, RequestStatusUpdated::class);
    }

    public function test_the_assigned_responder_is_notified_when_an_official_cancels_their_request(): void
    {
        Notification::fake();

        $official = User::factory()->create(['role' => UserRole::Official]);
        $resident = User::factory()->create(['role' => UserRole::Resident]);
        $request = $this->createRequest($resident, RequestStatus::Assigned);
        $personnel = $this->createPersonnel(['current_workload' => 1]);

        ResponseAssignment::create([
            'emergency_request_id' => $request->id,
            'response_personnel_id' => $personnel->id,
            'was_manual_override' => false,
        ]);

        $this->actingAs($official)
            ->patch(route('requests.updateStatus', $request), ['status' => 'cancelled']);

        Notification::assertSentTo(
            $personnel->user,
            RequestStatusUpdated::class,
            fn (RequestStatusUpdated $notification) => $notification->forResponder && $notification->newStatus === 'cancelled',
        );
        Notification::assertSentTo($resident, RequestStatusUpdated::class);
    }

    public function test_a_user_deletes_their_own_notification_but_not_someone_elses(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);
        $resident = User::factory()->pendingVerification()->create(['role' => UserRole::Resident]);
        $other = User::factory()->pendingVerification()->create(['role' => UserRole::Resident]);

        $this->actingAs($official)->patch(route('verifications.update', $resident), ['decision' => 'verified']);
        $this->actingAs($official)->patch(route('verifications.update', $other), ['decision' => 'verified']);

        $own = $resident->notifications()->sole();
        $othersNotification = $other->notifications()->sole();

        $this->actingAs($resident)
            ->from(route('notifications.index'))
            ->delete(route('notifications.destroy', $othersNotification->id))
            ->assertRedirect(route('notifications.index'));

        $this->assertSame(1, $other->notifications()->count());

        $this->actingAs($resident)
            ->from(route('notifications.index'))
            ->delete(route('notifications.destroy', $own->id))
            ->assertRedirect(route('notifications.index'));

        $this->assertSame(0, $resident->notifications()->count());
    }

    private function createRequest(User $resident, RequestStatus $status): EmergencyRequest
    {
        return EmergencyRequest::create([
            'resident_id' => $resident->id,
            'description' => 'Test emergency request.',
            'category' => 'general_assistance',
            'category_confidence' => 0.95,
            'urgency' => 'average',
            'urgency_confidence' => 0.95,
            'needs_review' => false,
            'latitude' => 13.5925,
            'longitude' => 124.2049,
            'status' => $status,
        ]);
    }

    private function createPersonnel(array $overrides = []): ResponsePersonnel
    {
        $user = User::factory()->create(['role' => UserRole::Personnel]);

        return ResponsePersonnel::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Test Responder',
            'specialization' => 'general_assistance',
            'is_available' => true,
            'current_workload' => 0,
            'latitude' => 13.5925,
            'longitude' => 124.2049,
        ], $overrides));
    }
}
