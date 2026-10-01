<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class ResponseAssignment extends Model
{
    use Auditable;

    protected $fillable = [
        'emergency_request_id',
        'response_personnel_id',
        'assignment_score',
        'distance_km',
        'specialization_match',
        'was_manual_override',
        'assigned_by',
        'assigned_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'assignment_score' => 'decimal:4',
            'distance_km' => 'decimal:2',
            'specialization_match' => 'boolean',
            'was_manual_override' => 'boolean',
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function emergencyRequest()
    {
        return $this->belongsTo(EmergencyRequest::class);
    }

    public function responsePersonnel()
    {
        return $this->belongsTo(ResponsePersonnel::class);
    }

    public function assignedByUser()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
