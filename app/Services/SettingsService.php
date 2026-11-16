<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActivityLog;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Settings;

final readonly class SettingsService
{
    /**
     * Save several settings groups at once: all of them or none. Each group
     * that actually changed gets one activity entry with before and after.
     *
     * @param  array<class-string<Settings>, array<string, mixed>>  $changes
     */
    public function update(array $changes): void
    {
        DB::transaction(function () use ($changes): void {
            foreach ($changes as $class => $values) {
                $this->updateGroup(resolve($class), $values);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function updateGroup(Settings $settings, array $values): void
    {
        $before = $settings->toArray();

        // Only properties the class declares. Anything else in the input is
        // not a setting, and spatie/laravel-settings would choke on it.
        $settings->fill(array_intersect_key($values, $before));
        $settings->save();

        $after = $settings->toArray();

        $changed = array_keys(array_filter(
            $after,
            fn (mixed $value, string $key): bool => ($before[$key] ?? null) !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($changed === []) {
            return;
        }

        activity(ActivityLog::Settings)
            ->event('updated')
            ->withProperties(['group' => $settings::group()])
            ->withChanges([
                'old' => array_intersect_key($before, array_flip($changed)),
                'attributes' => array_intersect_key($after, array_flip($changed)),
            ])
            ->log('updated');
    }
}
