<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature 2: SOS SMS fallback.
 */
class OutboundSmsMessage extends Model
{
    use Auditable;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const PURPOSE_SOS = 'sos';

    /** One-time code for the personnel mobile-number login. */
    public const PURPOSE_LOGIN_CODE = 'login_code';

    /** Tells a newly added responder their account is ready. */
    public const PURPOSE_ACCOUNT_CREATED = 'account_created';

    protected $fillable = [
        'user_id',
        'emergency_request_id',
        'purpose',
        'driver',
        'recipient',
        'body',
        'status',
        'failure_reason',
        'provider_reference',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function emergencyRequest()
    {
        return $this->belongsTo(EmergencyRequest::class);
    }

    public function wasSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }
}
