<?php

namespace App\Models;

use App\Enums\VulnerabilityTag;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class ResidentProfile extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id',
        'full_name',
        'birthdate',
        'sex',
        'civil_status',
        'purok_sitio',
        'address',
        'household_members_count',
        'vulnerability_tags',
        'emergency_contact_name',
        'emergency_contact_number',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'vulnerability_tags' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Feature 1: the household vulnerability markers on this profile.
     *
     * @return list<VulnerabilityTag>
     */
    public function vulnerabilityTagEnums(): array
    {
        return array_values(array_filter(array_map(
            fn (string $value) => VulnerabilityTag::tryFrom($value),
            array_filter((array) $this->vulnerability_tags, 'is_string'),
        )));
    }

    /**
     * @return list<string>
     */
    public function vulnerabilityTagValues(): array
    {
        return array_map(fn (VulnerabilityTag $tag) => $tag->value, $this->vulnerabilityTagEnums());
    }

    public function vulnerabilityTagLabels(): string
    {
        $labels = array_map(fn (VulnerabilityTag $tag) => $tag->shortLabel(), $this->vulnerabilityTagEnums());

        return $labels === [] ? 'Not declared' : implode(', ', $labels);
    }

    /**
     * True when the household declared at least one marker other than `none`,
     * which is what makes a request worth prioritising.
     */
    public function hasVulnerableMembers(): bool
    {
        foreach ($this->vulnerabilityTagEnums() as $tag) {
            if (! $tag->isExclusive()) {
                return true;
            }
        }

        return false;
    }
}
