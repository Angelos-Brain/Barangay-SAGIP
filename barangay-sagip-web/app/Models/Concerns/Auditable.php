<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature 3: Audit Log.
 *
 * Records create/update/delete on the model it is applied to. A model may
 * narrow what is captured with:
 *
 *   protected array $auditExcept = ['last_location_update'];
 *
 * Updates that touch only excluded attributes are not logged at all, which
 * keeps high-frequency noise (GPS pings) out of the trail.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $model->writeAuditEntry('created', null, $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            $after = $model->auditableAttributes($model->getChanges());

            if ($after === []) {
                return;
            }

            $before = array_intersect_key(
                $model->auditableAttributes($model->getOriginal()),
                $after,
            );

            $model->writeAuditEntry('updated', $before, $after);
        });

        static::deleted(function (Model $model): void {
            $model->writeAuditEntry('deleted', $model->auditableAttributes($model->getOriginal()), null);
        });
    }

    /**
     * The audit action prefix for this model, e.g. `emergency_request.updated`.
     */
    public function auditEventName(string $event): string
    {
        return $this->auditName().'.'.$event;
    }

    public function auditName(): string
    {
        return property_exists($this, 'auditName')
            ? $this->auditName
            : str(class_basename($this))->snake()->value();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function auditableAttributes(array $attributes): array
    {
        $excluded = array_merge(
            ['created_at', 'updated_at'],
            property_exists($this, 'auditExcept') ? $this->auditExcept : [],
        );

        return array_diff_key($attributes, array_flip($excluded));
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function writeAuditEntry(string $event, ?array $before, ?array $after): void
    {
        app(AuditLogger::class)->record(
            action: $this->auditEventName($event),
            subject: $this,
            before: $before,
            after: $after,
        );
    }
}
