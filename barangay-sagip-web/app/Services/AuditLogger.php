<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Feature 3: Audit Log.
 *
 * Single entry point for writing audit entries. Model changes reach it through
 * the Auditable trait; everything a model can't see (logins, rejected tanod
 * check-ins, SMS fallbacks) is recorded by calling record() directly.
 *
 * Writing an audit entry must never break the action being audited, so a
 * failure here is logged and swallowed.
 */
class AuditLogger
{
    /**
     * Attribute names that are never written to the trail, whatever model they
     * appear on.
     *
     * @var list<string>
     */
    public const REDACTED_ATTRIBUTES = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?string $description = null,
        ?User $actor = null,
    ): ?AuditLog {
        try {
            $actor ??= Auth::user();

            return AuditLog::create([
                'user_id' => $actor?->getKey(),
                'user_role' => $actor?->role,
                'user_label' => $actor?->name ?? 'system',
                'action' => $action,
                'auditable_type' => $subject?->getMorphClass(),
                'auditable_id' => $subject?->getKey(),
                'description' => $description,
                'before' => $before === null ? null : $this->redact($before),
                'after' => $after === null ? null : $this->redact($after),
                'ip_address' => $this->resolveIp(),
                'user_agent' => $this->resolveUserAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to write audit log entry: '.$e->getMessage(), [
                'action' => $action,
                'subject' => $subject === null ? null : $subject::class.'#'.$subject->getKey(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function redact(array $attributes): array
    {
        foreach (self::REDACTED_ATTRIBUTES as $attribute) {
            if (array_key_exists($attribute, $attributes)) {
                $attributes[$attribute] = '[redacted]';
            }
        }

        return array_map(
            fn (mixed $value) => $value instanceof \BackedEnum ? $value->value : $value,
            $attributes,
        );
    }

    protected function resolveIp(): ?string
    {
        return app()->runningInConsole() && ! app()->runningUnitTests()
            ? null
            : Request::ip();
    }

    protected function resolveUserAgent(): ?string
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        $agent = Request::userAgent();

        return $agent === null ? null : mb_substr($agent, 0, 500);
    }
}
