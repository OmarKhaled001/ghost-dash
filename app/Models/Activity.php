<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Override;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * @property int $id
 * @property string|null $log_name
 * @property string $description
 * @property string|null $event
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $causer_type
 * @property int|null $causer_id
 * @property Collection<string, mixed>|null $attribute_changes
 * @property Collection<string, mixed>|null $properties
 * @property CarbonImmutable|null $created_at
 * @property-read Model|null $subject
 * @property-read Model|null $causer
 */
final class Activity extends SpatieActivity
{
    /**
     * Attribute values before the change, keyed by attribute name.
     *
     * @return array<string, mixed>
     */
    public function oldValues(): array
    {
        return (array) ($this->attribute_changes?->get('old') ?? []);
    }

    /**
     * Attribute values after the change, keyed by attribute name.
     *
     * @return array<string, mixed>
     */
    public function newValues(): array
    {
        return (array) ($this->attribute_changes?->get('attributes') ?? []);
    }

    /**
     * What the given admin may see of the log. Super admins are invisible to
     * other admins, and a log that named them, with their email and IP
     * address on every sign-in, would undo that. So everything done by or to
     * a super admin is left out for a viewer who is not one.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, Admin $actor): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        $admin = (new Admin)->getMorphClass();
        $superAdmins = Admin::query()
            ->select('id')
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('name', Role::SUPER_ADMIN));

        foreach (['causer', 'subject'] as $party) {
            $query->where(fn (Builder $query): Builder => $query
                ->whereNull("{$party}_type")
                ->orWhere("{$party}_type", '!=', $admin)
                ->orWhereNotIn("{$party}_id", $superAdmins));
        }
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
