<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Feature 2: the optional photo or voice note sent after an SOS. Ownership of
 * the incident is checked by the controller.
 */
class StoreSosAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'attachment' => [
                'bail',
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/heic,audio/mpeg,audio/mp4,audio/aac,audio/ogg,audio/webm,audio/wav,audio/x-wav,audio/3gpp,video/webm',
                'max:'.(int) config('sagip.sos.attachment_max_kilobytes', 10240),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attachment.required' => 'Choose a photo or voice note to send.',
            'attachment.mimetypes' => 'Send a photo (JPG, PNG, WebP, HEIC) or a voice recording.',
            'attachment.max' => 'The attachment is too large.',
        ];
    }
}
