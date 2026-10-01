<?php

namespace App\Http\Requests;

use App\Enums\SosReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Feature 2: SOS payload — the resident's live coordinates, how accurate the
 * device claims they are, when the button was pressed, and why. The user id is
 * taken from the session and never accepted from the request.
 */
class StoreSosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'triggered_at' => ['nullable', 'date'],
            'reason' => ['required', Rule::enum(SosReason::class)],
            // TrimStrings + ConvertEmptyStringsToNull turn a blank answer into
            // null, so whitespace-only text fails `required_if` as well.
            'reason_other' => [
                Rule::requiredIf(fn () => $this->input('reason') === SosReason::Other->value),
                'nullable',
                'string',
                'max:'.SosReason::OTHER_MAX_LENGTH,
            ],
            'device_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.required' => 'Your location could not be read. Turn on GPS and try again.',
            'longitude.required' => 'Your location could not be read. Turn on GPS and try again.',
            'reason.required' => 'Choose what the emergency is before sending the SOS.',
            'reason_other.required' => 'Describe the emergency in a few words.',
            'reason_other.max' => 'Keep the description under '.SosReason::OTHER_MAX_LENGTH.' characters.',
        ];
    }

    /**
     * @return array{latitude: string, longitude: string, accuracy: float|null}
     */
    public function location(): array
    {
        return [
            'latitude' => (string) $this->validated('latitude'),
            'longitude' => (string) $this->validated('longitude'),
            'accuracy' => $this->validated('accuracy') === null
                ? null
                : (float) $this->validated('accuracy'),
        ];
    }

    /**
     * The reason and device context that travel with the incident. Free text is
     * kept only when the resident actually chose "Other".
     *
     * @return array{reason: SosReason, reason_other: string|null, device_id: string|null}
     */
    public function details(): array
    {
        $reason = SosReason::from($this->validated('reason'));

        return [
            'reason' => $reason,
            'reason_other' => $reason === SosReason::Other ? $this->validated('reason_other') : null,
            'device_id' => $this->validated('device_id'),
        ];
    }
}
