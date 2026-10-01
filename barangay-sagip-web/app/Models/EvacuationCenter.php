<?php

namespace App\Models;

use App\Enums\EvacuationCenterStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature 4: Evacuation Center Management.
 */
class EvacuationCenter extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'capacity',
        'current_occupancy',
        'status',
        'contact_person',
        'contact_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'capacity' => 'integer',
            'current_occupancy' => 'integer',
            'status' => EvacuationCenterStatus::class,
        ];
    }

    public function remainingCapacity(): int
    {
        return max(0, $this->capacity - $this->current_occupancy);
    }

    public function occupancyPercentage(): int
    {
        if ($this->capacity <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->current_occupancy / $this->capacity) * 100));
    }

    public function isOverCapacity(): bool
    {
        return $this->current_occupancy > $this->capacity;
    }

    /**
     * A centre only takes evacuees when it is open and has room left.
     */
    public function canAcceptEvacuees(): bool
    {
        return $this->status->acceptsEvacuees() && $this->remainingCapacity() > 0;
    }

    public function occupancyBadgeColor(): string
    {
        return match (true) {
            $this->occupancyPercentage() >= 100 => 'red',
            $this->occupancyPercentage() >= 80 => 'orange',
            $this->occupancyPercentage() >= 50 => 'yellow',
            default => 'green',
        };
    }
}
