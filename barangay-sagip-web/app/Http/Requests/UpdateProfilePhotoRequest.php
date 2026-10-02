<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared validation for every profile photo upload. Photos only come from the
 * camera face capture (x-photo-upload), which always produces a square JPEG of
 * CAPTURE_SIZE pixels, so anything else is rejected as not camera-captured.
 * Authorization is left to the route middleware and controller, which decide
 * whose photo is changed.
 */
class UpdateProfilePhotoRequest extends FormRequest
{
    public const MAX_KILOBYTES = 2048;

    public const CAPTURE_SIZE = 512;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // 'dimensions' runs getimagesize(), so a renamed, corrupted, or non-captured image fails even with a valid extension.
            'photo' => ['bail', 'required', 'file', 'mimes:jpg,jpeg', 'max:'.self::MAX_KILOBYTES, 'dimensions:width='.self::CAPTURE_SIZE.',height='.self::CAPTURE_SIZE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Take a face photo with your camera first.',
            'photo.mimes' => 'Photos must be taken with the camera face capture. Uploaded files are not accepted.',
            'photo.max' => 'The photo must be 2 MB or smaller.',
            'photo.dimensions' => 'Photos must be taken with the camera face capture. Uploaded files are not accepted.',
            'photo.uploaded' => 'The photo could not be uploaded. Please retake it.',
        ];
    }
}
