<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores a user's profile photo on the public disk and removes the file it
 * replaces, so each user has at most one photo on disk.
 */
class ProfilePhotoService
{
    public const DISK = 'public';

    public const DIRECTORY = 'profile-photos';

    public function replace(User $user, UploadedFile $photo): void
    {
        $previousPath = $user->profile_photo_path;

        $user->forceFill([
            'profile_photo_path' => $photo->store(self::DIRECTORY, self::DISK),
        ])->save();

        if ($previousPath) {
            Storage::disk(self::DISK)->delete($previousPath);
        }
    }
}
