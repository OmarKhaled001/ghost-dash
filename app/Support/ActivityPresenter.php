<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ActivityLog;
use App\Enums\AuthActivity;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\Role;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Turns stored activity rows into text an admin can read, in the current
 * language. Kept out of the model so that logging stays free of UI concerns.
 */
final class ActivityPresenter
{
    /**
     * Events for the filter, including model events and pivot syncs.
     *
     * @return array<string, string>
     */
    public static function eventOptions(): array
    {
        $events = [
            'created', 'updated', 'deleted', 'roles_synced', 'permissions_synced',
            ...array_map(fn (AuthActivity $event): string => $event->value, AuthActivity::cases()),
        ];

        return collect($events)
            ->mapWithKeys(fn (string $event): array => [$event => self::event($event)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function logOptions(): array
    {
        return collect(ActivityLog::cases())
            ->mapWithKeys(fn (ActivityLog $log): array => [$log->value => $log->label()])
            ->all();
    }

    /**
     * Model types that appear as causer or subject, keyed by morph class.
     *
     * @return array<string, string>
     */
    public static function modelOptions(): array
    {
        return collect([Admin::class, User::class, Role::class])
            ->mapWithKeys(fn (string $class): array => [(new $class)->getMorphClass() => self::model($class)])
            ->all();
    }

    public static function event(?string $event): string
    {
        return self::translate('admin.activity.events', $event);
    }

    public static function log(?string $log): string
    {
        return self::translate('admin.activity.logs', $log);
    }

    public static function attribute(string $attribute): string
    {
        return self::translate('admin.fields', $attribute);
    }

    /**
     * @param  class-string|string|null  $type  A class name or a morph alias.
     */
    public static function model(?string $type): string
    {
        if ($type === null) {
            return '—';
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        return match ($class) {
            Admin::class => Str::ucfirst(__('admin.admins.label')),
            User::class => Str::ucfirst(__('admin.users.label')),
            Role::class => Str::ucfirst(__('filament-shield::filament-shield.resource.label.role')),
            default => Str::headline(class_basename($class)),
        };
    }

    /**
     * "Admin: Omar" when the record still exists, "Admin #12" once it is gone.
     */
    public static function record(?string $type, int|string|null $id, ?Model $record): string
    {
        if ($type === null) {
            return '—';
        }

        $name = $record?->getAttribute('name');

        return filled($name)
            ? self::model($type).': '.$name
            : self::model($type).' #'.$id;
    }

    /**
     * @return list<array{label: string, old: string, new: string}>
     */
    public static function diff(Activity $activity): array
    {
        $old = $activity->oldValues();
        $new = $activity->newValues();

        // A settings change names its group; the labels live with its tab.
        $group = $activity->properties?->get('group');
        $labels = is_string($group) && $group !== '' ? "settings/{$group}.fields" : null;

        return collect(array_keys([...$old, ...$new]))
            ->map(fn (string $attribute): array => [
                'label' => $labels !== null && Lang::has("{$labels}.{$attribute}")
                    ? __("{$labels}.{$attribute}")
                    : self::attribute($attribute),
                'old' => array_key_exists($attribute, $old) ? self::value($old[$attribute]) : '—',
                'new' => array_key_exists($attribute, $new) ? self::value($new[$attribute]) : '—',
            ])
            ->values()
            ->all();
    }

    /**
     * Properties flattened for a key/value list.
     *
     * @return array<string, string>
     */
    public static function properties(Activity $activity): array
    {
        return collect($activity->properties?->all() ?? [])
            ->mapWithKeys(fn (mixed $value, string|int $key): array => [
                self::translate('admin.activity.properties', (string) $key) => self::value($value),
            ])
            ->all();
    }

    public static function value(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => '—',
            is_bool($value) => $value ? __('admin.activity.values.yes') : __('admin.activity.values.no'),
            $value instanceof BackedEnum => (string) $value->value,
            is_array($value) => $value === [] ? '—' : implode(', ', array_map(self::value(...), $value)),
            default => (string) $value,
        };
    }

    private static function translate(string $group, ?string $key): string
    {
        if ($key === null || $key === '') {
            return '—';
        }

        return Lang::has("{$group}.{$key}")
            ? __("{$group}.{$key}")
            : Str::headline($key);
    }
}
