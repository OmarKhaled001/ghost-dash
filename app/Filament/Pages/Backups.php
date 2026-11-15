<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\CustomPermission;
use App\Filament\Concerns\AuthorizesWithPermission;
use App\Filament\Contracts\GatedByPermission;
use App\Services\BackupService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\ArrayRecord;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Number;
use Override;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class Backups extends Page implements GatedByPermission, HasTable
{
    use AuthorizesWithPermission;
    use InteractsWithTable;

    protected string $view = 'filament.pages.backups';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?int $navigationSort = 20;

    public static function gatePermission(): string
    {
        return CustomPermission::ManageBackups->value;
    }

    #[Override]
    public static function getNavigationGroup(): string
    {
        return __('admin.navigation.system');
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('admin.backups.title');
    }

    #[Override]
    public function getTitle(): string
    {
        return __('admin.backups.title');
    }

    public function table(Table $table): Table
    {
        return $table
            // Archives are files on a disk, not rows in a table. The path is
            // only the row key: BackupService re-checks it against the real
            // list of backups before touching anything.
            ->records(fn (): array => resolve(BackupService::class)->list()
                ->mapWithKeys(fn (array $backup): array => [
                    $backup['path'] => [ArrayRecord::getKeyName() => $backup['path'], ...$backup],
                ])
                ->all())
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.backups.columns.name')),

                TextColumn::make('size')
                    ->label(__('admin.backups.columns.size'))
                    ->formatStateUsing(fn (int $state): string => (string) Number::fileSize($state, precision: 1)),

                TextColumn::make('created_at')
                    ->label(__('admin.backups.columns.created_at'))
                    ->dateTime(),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('admin.backups.empty.heading'))
            ->emptyStateDescription(__('admin.backups.empty.description', ['disk' => resolve(BackupService::class)->disk()]))
            ->recordActions([
                Action::make('download')
                    ->label(__('admin.backups.actions.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn (array $record): StreamedResponse => resolve(BackupService::class)->download($record['path'])),

                Action::make('delete')
                    ->label(__('admin.backups.actions.delete'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (array $record): void {
                        resolve(BackupService::class)->delete($record['path']);

                        Notification::make()
                            ->title(__('admin.backups.notifications.deleted'))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            $this->createAction('create', onlyDatabase: false)
                ->label(__('admin.backups.actions.create'))
                ->icon(Heroicon::OutlinedPlus),

            $this->createAction('createDatabaseOnly', onlyDatabase: true)
                ->label(__('admin.backups.actions.create_database_only'))
                ->color('gray'),
        ];
    }

    private function createAction(string $name, bool $onlyDatabase): Action
    {
        return Action::make($name)
            ->requiresConfirmation()
            ->modalDescription(__('admin.backups.actions.create_confirmation'))
            ->action(function () use ($onlyDatabase): void {
                resolve(BackupService::class)->request($onlyDatabase);

                Notification::make()
                    ->title(__('admin.backups.notifications.requested.title'))
                    ->body(__('admin.backups.notifications.requested.body'))
                    ->success()
                    ->send();
            });
    }
}
