<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Requests\UpdateProfilePhotoRequest;
use App\Models\ResponsePersonnel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    /**
     * @return array<string, array{0: UserRole}>
     */
    public static function roles(): array
    {
        return [
            'resident' => [UserRole::Resident],
            'personnel' => [UserRole::Personnel],
            'official' => [UserRole::Official],
        ];
    }

    #[DataProvider('roles')]
    public function test_each_role_can_upload_their_own_photo(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get(route('account.photo.edit'))->assertOk();

        $this->actingAs($user)
            ->put(route('account.photo.update'), ['photo' => $this->capture()])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $path = $user->fresh()->profile_photo_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)->get(route('account.photo.edit'))->assertSee(Storage::disk('public')->url($path));
    }

    public function test_replacing_a_photo_deletes_the_previous_file(): void
    {
        $user = User::factory()->create(['role' => UserRole::Resident]);

        $this->actingAs($user)->put(route('account.photo.update'), ['photo' => $this->capture()]);
        $firstPath = $user->fresh()->profile_photo_path;

        $this->actingAs($user)->put(route('account.photo.update'), ['photo' => $this->capture()]);
        $secondPath = $user->fresh()->profile_photo_path;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    /**
     * @return array<string, array{0: \Closure(): UploadedFile, 1: string}>
     */
    public static function invalidPhotos(): array
    {
        return [
            'wrong type' => [fn () => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'), 'camera face capture'],
            'png gallery photo' => [fn () => UploadedFile::fake()->image('gallery.png', 512, 512), 'camera face capture'],
            'jpeg not from the capture' => [fn () => UploadedFile::fake()->image('gallery.jpg', 1080, 1440), 'camera face capture'],
            'too large' => [fn () => UploadedFile::fake()->image('big.jpg', 512, 512)->size(3000), '2 MB or smaller'],
            'text renamed to jpg' => [fn () => UploadedFile::fake()->createWithContent('broken.jpg', 'this is not really an image'), 'camera face capture'],
            'truncated jpeg' => [fn () => UploadedFile::fake()->createWithContent('truncated.jpg', "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00".str_repeat("\x00", 64)), 'camera face capture'],
            'missing' => [fn () => null, 'Take a face photo'],
        ];
    }

    #[DataProvider('invalidPhotos')]
    public function test_invalid_photos_are_rejected_with_a_clear_message(\Closure $makeFile, string $message): void
    {
        $user = User::factory()->create(['role' => UserRole::Resident]);

        $this->actingAs($user)
            ->from(route('account.photo.edit'))
            ->put(route('account.photo.update'), ['photo' => $makeFile()])
            ->assertRedirect(route('account.photo.edit'))
            ->assertSessionHasErrors('photo');

        $errors = session('errors')->get('photo');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString($message, $errors[0]);

        $this->assertNull($user->fresh()->profile_photo_path);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_guests_cannot_upload_photos(): void
    {
        $this->put(route('account.photo.update'), ['photo' => $this->capture()])
            ->assertRedirect(route('login'));
    }

    public function test_official_can_set_a_linked_personnel_photo(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);
        $personnelUser = User::factory()->create(['role' => UserRole::Personnel]);
        $personnel = $this->makePersonnel($personnelUser);

        $this->actingAs($official)->get(route('personnel.edit', $personnel))->assertOk()->assertSee('Take Face Photo');

        $this->actingAs($official)
            ->put(route('personnel.photo.update', $personnel), ['photo' => $this->capture()])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNotNull($personnelUser->fresh()->profile_photo_path);
        $this->assertNull($official->fresh()->profile_photo_path);

        $this->actingAs($official)
            ->get(route('personnel.index'))
            ->assertSee(Storage::disk('public')->url($personnelUser->fresh()->profile_photo_path));
    }

    public function test_official_cannot_set_photo_on_unlinked_personnel_record(): void
    {
        $official = User::factory()->create(['role' => UserRole::Official]);
        $personnel = $this->makePersonnel(null);

        $this->actingAs($official)
            ->put(route('personnel.photo.update', $personnel), ['photo' => $this->capture()])
            ->assertStatus(422);

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    #[DataProvider('nonOfficialRoles')]
    public function test_non_officials_cannot_change_another_users_photo(UserRole $role): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $personnelUser = User::factory()->create(['role' => UserRole::Personnel]);
        $personnel = $this->makePersonnel($personnelUser);

        $this->actingAs($actor)
            ->put(route('personnel.photo.update', $personnel), ['photo' => $this->capture()])
            ->assertForbidden();

        $this->assertNull($personnelUser->fresh()->profile_photo_path);
    }

    /**
     * @return array<string, array{0: UserRole}>
     */
    public static function nonOfficialRoles(): array
    {
        return [
            'resident' => [UserRole::Resident],
            'personnel' => [UserRole::Personnel],
        ];
    }

    public function test_own_photo_route_ignores_a_user_id_in_the_request(): void
    {
        $attacker = User::factory()->create(['role' => UserRole::Resident]);
        $victim = User::factory()->create(['role' => UserRole::Resident]);

        $this->actingAs($attacker)->put(route('account.photo.update'), [
            'photo' => $this->capture(),
            'user_id' => $victim->id,
        ]);

        $this->assertNull($victim->fresh()->profile_photo_path);
        $this->assertNotNull($attacker->fresh()->profile_photo_path);
    }

    public function test_placeholder_initials_show_when_no_photo_is_uploaded(): void
    {
        $user = User::factory()->create(['role' => UserRole::Personnel, 'name' => 'Juan Dela Cruz']);

        $this->actingAs($user)->get(route('account.photo.edit'))->assertOk()->assertSee('JD');
    }

    public function test_photo_form_offers_camera_capture_only(): void
    {
        $user = User::factory()->create(['role' => UserRole::Resident]);

        $this->actingAs($user)
            ->get(route('account.photo.edit'))
            ->assertSee('Take Face Photo')
            ->assertSee('faceCapture', false)
            ->assertDontSee('accept="image/jpeg,image/png,image/webp"', false);
    }

    /**
     * Mirrors what the camera face capture submits: a square JPEG of the capture size.
     */
    private function capture(): UploadedFile
    {
        return UploadedFile::fake()->image('face.jpg', UpdateProfilePhotoRequest::CAPTURE_SIZE, UpdateProfilePhotoRequest::CAPTURE_SIZE);
    }

    private function makePersonnel(?User $user): ResponsePersonnel
    {
        return ResponsePersonnel::create([
            'user_id' => $user?->id,
            'name' => $user?->name ?? 'Unlinked Responder',
            'specialization' => 'medical',
            'latitude' => 13.5925,
            'longitude' => 124.2049,
            'is_available' => true,
        ]);
    }
}
