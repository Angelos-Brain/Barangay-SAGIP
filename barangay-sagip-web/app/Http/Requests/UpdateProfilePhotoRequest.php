<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared validation for every profile photo upload. Authorization is left to
 * the route middleware and controller, which decide whose photo is changed.
 */
class UpdateProfilePhotoRequest extends FormRequest
{
    public const MAX_KILOBYTES = 2048;

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
            // 'dimensions' runs getimagesize(), so a renamed or corrupted file fails even with a valid extension.
            'photo' => ['bail', 'required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KILOBYTES, 'dimensions:min_width=1,min_height=1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Choose a photo to upload.',
            'photo.mimes' => 'The photo must be a JPG, PNG, or WebP image.',
            'photo.max' => 'The photo must be 2 MB or smaller.',
            'photo.dimensions' => 'That file could not be read as an image. It may be corrupted — try a different photo.',
            'photo.uploaded' => 'The photo could not be uploaded. It may be larger than 2 MB.',
        ];
    }
}
