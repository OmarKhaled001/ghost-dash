<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Support\GuardRails;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AdminRepository;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class StatsOverview extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    /**
     * @return array<int, Stat>
     */
    #[Override]
    protected function getStats(): array
    {
        // Counted through the repository so that an admin who cannot see super
        // admins cannot infer how many there are from this number either.
        $admins = resolve(AdminRepository::class)->queryVisibleTo(GuardRails::actor());

        return [
            Stat::make(__('admin.users.plural'), User::query()->count())
                ->description(__('admin.widgets.stats.users_today', [
                    'count' => User::query()->whereDate('created_at', today())->count(),
                ]))
                ->icon(Heroicon::OutlinedUsers),

            Stat::make(__('admin.admins.plural'), (clone $admins)->count())
                ->description(__('admin.widgets.stats.admins_active', [
                    'count' => (clone $admins)->where('is_active', true)->count(),
                ]))
                ->icon(Heroicon::OutlinedUserGroup),

            Stat::make(__('filament-shield::filament-shield.resource.label.roles'), Role::query()->where('guard_name', Role::GUARD)->count())
                ->icon(Heroicon::OutlinedShieldCheck),
        ];
    }
}
