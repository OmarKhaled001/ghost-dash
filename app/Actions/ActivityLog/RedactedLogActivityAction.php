<?php

declare(strict_types=1);

namespace App\Actions\ActivityLog;

use App\Support\SensitiveData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Override;
use Spatie\Activitylog\Actions\LogActivityAction;

/**
 * Masks sensitive values in every activity before it is stored.
 *
 * Doing this once, at the single point all activities pass through, means a
 * model that forgets to exclude its password column still cannot leak it,
 * and neither can a careless withProperties() call.
 */
final class RedactedLogActivityAction extends LogActivityAction
{
    public const string MASK = SensitiveData::MASK;

    #[Override]
    protected function transformChanges(Model $activity): void
    {
        foreach (['attribute_changes', 'properties'] as $column) {
            $value = $activity->getAttribute($column);

            if ($value instanceof Collection) {
                $value = $value->all();
            }

            if (is_array($value)) {
                $activity->setAttribute($column, SensitiveData::redact($value));
            }
        }
    }
}
