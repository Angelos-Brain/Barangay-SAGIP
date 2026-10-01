<?php

namespace App\Models;

use App\Enums\Specialization;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class ResponsePersonnel extends Model
{
    use Auditable;

    protected $table = 'response_personnel';

    protected $fillable = [
        'user_id',
        'name',
        'specialization',
        'specializations',
        'phone_number',
        'latitude',
        'longitude',
        'is_available',
        'unavailability_reason',
        'current_workload',
        'last_location_update',
        'on_duty_at',
        'last_check_in_latitude',
        'last_check_in_longitude',
        'last_check_in_distance_meters',
    ];

    /**
     * GPS pings land here every few seconds while a responder is on the road;
     * they are movement, not a reviewable state change.
     *
     * @var list<string>
     */
    protected array $auditExcept = [
        'latitude',
        'longitude',
        'last_location_update',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'last_location_update' => 'datetime',
            'specializations' => 'array',
            'on_duty_at' => 'datetime',
            'last_check_in_latitude' => 'decimal:7',
            'last_check_in_longitude' => 'decimal:7',
            'last_check_in_distance_meters' => 'decimal:2',
        ];
    }

    /**
     * Keeps the original single `specialization` column in step with the tag
     * list, so the ML assignment payload and legacy views never see a blank
     * specialization on a tagged responder.
     */
    protected static function booted(): void
    {
        static::saving(function (self $personnel): void {
            $tags = array_values(array_filter((array) $personnel->specializations, 'is_string'));

            if ($tags !== []) {
                $personnel->specialization = $tags[0];
            } elseif (filled($personnel->specialization)) {
                $personnel->specializations = [$personnel->specialization];
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignments()
    {
        return $this->hasMany(ResponseAssignment::class);
    }

    public function activeAssignments()
    {
        return $this->assignments()->whereNull('completed_at');
    }

    /**
     * Feature 10: the responder's specialization tags. Falls back to the
     * original single `specialization` column for rows created before tags
     * existed, so this never returns an empty list for a valid record.
     *
     * @return list<Specialization>
     */
    public function specializationEnums(): array
    {
        $values = $this->specializations;

        if (blank($values)) {
            $values = array_filter([$this->specialization]);
        }

        return array_values(array_filter(array_map(
            fn (string $value) => Specialization::tryFrom($value),
            array_filter((array) $values, 'is_string'),
        )));
    }

    /**
     * @return list<string>
     */
    public function specializationValues(): array
    {
        return array_map(fn (Specialization $s) => $s->value, $this->specializationEnums());
    }

    public function specializationLabels(): string
    {
        $labels = array_map(fn (Specialization $s) => $s->label(), $this->specializationEnums());

        return $labels === [] ? '—' : implode(', ', $labels);
    }

    public function hasSpecialization(Specialization $specialization): bool
    {
        return in_array($specialization, $this->specializationEnums(), true);
    }

    /**
     * Incident categories this responder can be dispatched to.
     *
     * @return list<string>
     */
    public function incidentCategories(): array
    {
        $categories = [];

        foreach ($this->specializationEnums() as $specialization) {
            foreach ($specialization->incidentCategories() as $category) {
                $categories[$category] = true;
            }
        }

        return array_keys($categories);
    }

    /**
     * Kept for backwards compatibility with existing views, the seeder, and the
     * ML assignment payload.
     *
     * @return list<string>
     */
    public static function specializations(): array
    {
        return Specialization::values();
    }
}
