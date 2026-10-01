<?php

namespace App\Http\Requests;

use App\Enums\EvacuationCenterStatus;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Feature 4: Evacuation Center Management. Used for both create and update —
 * the rules are identical either way.
 */
class StoreEvacuationCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::EvacuationCentersManage->value) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'current_occupancy' => ['required', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', 'string', 'in:'.implode(',', EvacuationCenterStatus::values())],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $capacity = (int) $this->input('capacity');
            $occupancy = (int) $this->input('current_occupancy');

            if ($capacity > 0 && $occupancy > $capacity) {
                $validator->errors()->add(
                    'current_occupancy',
                    'Occupancy cannot exceed capacity. Raise the capacity or move evacuees to another center.',
                );
            }
        });
    }
}
