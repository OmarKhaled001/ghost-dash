<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\CustomPermission;
use App\Filament\Concerns\AuthorizesWithPermission;
use App\Filament\Contracts\GatedByPermission;
use App\Services\HealthService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Override;

final class Health extends Page implements GatedByPermission
{
    use AuthorizesWithPermission;

    protected string $view = 'filament.pages.health';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?int $navigationSort = 30;

    public static function gatePermission(): string
    {
        return CustomPermission::ViewHealth->value;
    }

    #[Override]
    public static function getNavigationGroup(): string
    {
        return __('admin.navigation.system');
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('admin.health.title');
    }

    #[Override]
    public function getTitle(): string
    {
        return __('admin.health.title');
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function getViewData(): array
    {
        $health = resolve(HealthService::class);

        return [
            'lastRanAt' => $health->lastRanAt(),
            'checks' => $health->latest()->map(fn (array $check): array => [
                ...$check,
                // Results are stored by the scheduler, in whatever language
                // it ran in. The name is stable, so translate from that.
                'label' => $this->translate('admin.health.checks', Str::snake($check['name']), $check['label']),
                'status_label' => $this->translate('admin.health.statuses', $check['status'], Str::headline($check['status'])),
                'color' => match ($check['status']) {
                    'ok' => 'success',
                    'warning' => 'warning',
                    'skipped' => 'gray',
                    default => 'danger',
                },
            ])->all(),
        ];
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label(__('admin.health.actions.refresh'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function (): void {
                    resolve(HealthService::class)->run();

                    Notification::make()
                        ->title(__('admin.health.notifications.refreshed'))
                        ->success()
                        ->send();
                }),
        ];
    }

    private function translate(string $group, string $key, string $fallback): string
    {
        return Lang::has("{$group}.{$key}") ? __("{$group}.{$key}") : $fallback;
    }
}
