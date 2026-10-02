<?php

namespace App\Http\Requests;

use App\Enums\VulnerabilityTag;
use Illuminate\Foundation\Http\FormRequest;

class UpdateResidentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isResident() ?? false;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'birthdate' => ['required', 'date', 'before:today'],
            'sex' => ['required', 'in:male,female'],
            'civil_status' => ['required', 'in:single,married,widowed,separated'],
            'purok_sitio' => ['required', 'string', 'max:255'],
            'address' => [
                'required',
                'string',
                'max:500',
                'regex:/^\s*(?:house\s+|unit\s+|#\s*)?\d+[A-Za-z]?(?:[-\/]\d+[A-Za-z0-9]*)?\s*,\s*[^,\s][^,]*\s*,\s*[^,\s][^,]*\s*,\s*[^,\s][^,]*\s*,\s*[^,\s][^,]*\s*$/iu',
            ],
            'household_members_count' => ['required', 'integer', 'min:1', 'max:50'],
            'vulnerability_tags' => ['required', 'array', 'min:1'],
            'vulnerability_tags.*' => ['string', 'in:'.implode(',', VulnerabilityTag::values())],
            'emergency_contact_name' => ['required', 'string', 'max:255'],
            'emergency_contact_number' => ['required', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'address.regex' => 'Address must follow: House/Unit Number, Street/Road, Barangay, Municipality/City, Province. Example: 123, Sample Street, Calatagan Tibang, Virac, Catanduanes.',
            'vulnerability_tags.required' => 'Select at least one household vulnerability, or "None of the above".',
            'vulnerability_tags.*.in' => 'Choose vulnerability markers from the list provided.',
        ];
    }

    /**
     * Feature 1: "None of the above" is mutually exclusive, and an omitted
     * field must clear the stored tags rather than leave stale markers behind.
     *
     * This normalises before the rules run so the cleaned list is what
     * validated() returns and what gets persisted.
     */
    protected function prepareForValidation(): void
    {
        $tags = array_values(array_unique(array_filter(
            (array) $this->input('vulnerability_tags', []),
            'is_string',
        )));

        if (in_array(VulnerabilityTag::None->value, $tags, true)) {
            $tags = [VulnerabilityTag::None->value];
        }

        $this->merge(['vulnerability_tags' => $tags]);
    }
}
