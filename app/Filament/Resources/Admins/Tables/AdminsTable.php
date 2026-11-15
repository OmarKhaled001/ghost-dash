<?php

declare(strict_types=1);

namespace App\Filament\Resources\Admins\Tables;

use App\Filament\Support\GuardRails;
use App\Filament\Support\InitialsAvatarProvider;
use App\Models\Admin;
use App\Models\Role;
use App\Services\AdminService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class AdminsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_path')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn (Admin $record): string => InitialsAvatarProvider::for($record->name)),

                TextColumn::make('name')
                    ->label(__('admin.fields.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('admin.fields.email'))
                    ->searchable()
                    ->copyable(),

                TextColumn::make('roles.name')
                    ->label(__('admin.fields.roles'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Str::headline($state)),

                IconColumn::make('is_active')
                    ->label(__('admin.fields.is_active'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('last_login_at')
                    ->label(__('admin.fields.last_login_at'))
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder(__('admin.admins.never_signed_in'))
                    ->sortable(),

                TextColumn::make('last_login_ip')
                    ->label(__('admin.fields.last_login_ip'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.fields.is_active')),

                SelectFilter::make('roles')
                    ->label(__('admin.fields.roles'))
                    ->relationship(
                        'roles',
                        'name',
                        // The filter must not reveal that a super_admin role
                        // exists to someone who cannot see its members.
                        fn (Builder $query): Builder => $query
                            ->where('guard_name', Role::GUARD)
                            ->unless(GuardRails::actor()->isSuperAdmin(), fn (Builder $roles): Builder => $roles->where('name', '!=', Role::SUPER_ADMIN)),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Role $record): string => Str::headline($record->name))
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    self::deactivateAction(),
                    self::activateAction(),
                    self::deleteAction(),
                ]),
            ]);
    }

    public static function deactivateAction(): Action
    {
        return Action::make('deactivate')
            ->label(__('admin.admins.actions.deactivate'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(__('admin.admins.actions.deactivate_confirmation'))
            ->authorize('deactivate')
            ->visible(fn (Admin $record): bool => $record->is_active)
            ->action(function (Admin $record, Action $action): void {
                GuardRails::attempt(fn (): Admin => resolve(AdminService::class)->deactivate(GuardRails::actor(), $record))
                    ? $action->success()
                    : $action->failure();
            })
            ->successNotificationTitle(__('admin.admins.notifications.deactivated'));
    }

    public static function activateAction(): Action
    {
        return Action::make('activate')
            ->label(__('admin.admins.actions.activate'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->authorize('update')
            ->visible(fn (Admin $record): bool => ! $record->is_active)
            ->action(function (Admin $record, Action $action): void {
                GuardRails::attempt(fn (): Admin => resolve(AdminService::class)->activate(GuardRails::actor(), $record))
                    ? $action->success()
                    : $action->failure();
            })
            ->successNotificationTitle(__('admin.admins.notifications.activated'));
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->using(fn (Admin $record): bool => GuardRails::attempt(
                fn () => resolve(AdminService::class)->delete(GuardRails::actor(), $record),
            ));
    }
}
