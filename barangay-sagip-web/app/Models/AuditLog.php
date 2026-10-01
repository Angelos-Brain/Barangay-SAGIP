<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Feature 3: Audit Log.
 *
 * Append-only by construction: the model has no `updated_at`, and the
 * updating/deleting events throw rather than silently allowing history to be
 * rewritten from application code.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'user_role',
        'user_label',
        'action',
        'auditable_type',
        'auditable_id',
        'description',
        'before',
        'after',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'user_role' => UserRole::class,
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Audit log entries are append-only and cannot be modified.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Audit log entries are append-only and cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Human-readable name of the record this entry is about.
     */
    public function subjectLabel(): string
    {
        if ($this->auditable_type === null) {
            return '—';
        }

        return sprintf('%s #%s', class_basename($this->auditable_type), $this->auditable_id ?? '?');
    }

    /**
     * Attributes that actually moved, as `field => [before, after]`.
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function changes(): array
    {
        $before = $this->before ?? [];
        $after = $this->after ?? [];
        $changes = [];

        foreach (array_keys($before + $after) as $field) {
            $changes[$field] = [$before[$field] ?? null, $after[$field] ?? null];
        }

        return $changes;
    }

    public function scopeForAction(Builder $query, ?string $action): Builder
    {
        return $query->when($action, fn (Builder $q) => $q->where('action', $action));
    }

    public function scopeForUser(Builder $query, int|string|null $userId): Builder
    {
        return $query->when($userId, fn (Builder $q) => $q->where('user_id', $userId));
    }
}
