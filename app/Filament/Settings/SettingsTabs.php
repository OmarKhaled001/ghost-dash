<?php

declare(strict_types=1);

namespace App\Filament\Settings;

use App\Models\Admin;
use App\Support\Discovery;
use Illuminate\Support\Collection;
use Spatie\LaravelSettings\Settings;

/**
 * The tabs of the Settings page: every SettingsTab in
 * app/Filament/Settings/Tabs, plus any listed in ghostdash.settings.tabs
 * (for tabs that live elsewhere, such as in a package).
 */
final class SettingsTabs
{
    /**
     * @return Collection<int, SettingsTab<Settings>>
     */
    public function all(): Collection
    {
        $discovered = Discovery::classes(
            app_path('Filament/Settings/Tabs'),
            app()->getNamespace().'Filament\\Settings\\Tabs',
            SettingsTab::class,
        );

        /** @var list<class-string<SettingsTab<Settings>>> $listed */
        $listed = config('ghostdash.settings.tabs', []);

        /** @var Collection<int, SettingsTab<Settings>> $tabs */
        $tabs = collect([...$discovered, ...$listed])
            ->unique()
            ->map(fn (string $class): SettingsTab => resolve($class));

        return $tabs
            ->sortBy([
                fn (SettingsTab $a, SettingsTab $b): int => $a->sort() <=> $b->sort(),
                fn (SettingsTab $a, SettingsTab $b): int => strcmp($a->label(), $b->label()),
            ])
            ->values();
    }

    /**
     * @return Collection<int, SettingsTab<Settings>>
     */
    public function visibleTo(Admin $admin): Collection
    {
        return $this->all()
            ->filter(fn (SettingsTab $tab): bool => $admin->can($tab->permission()))
            ->values();
    }
}
