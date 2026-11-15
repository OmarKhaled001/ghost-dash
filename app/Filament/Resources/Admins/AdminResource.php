<?php

declare(strict_types=1);

namespace App\Filament\Resources\Admins;

use App\Filament\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\Admins\Pages\EditAdmin;
use App\Filament\Resources\Admins\Pages\ListAdmins;
use App\Filament\Resources\Admins\Pages\ViewAdmin;
use App\Filament\Resources\Admins\Schemas\AdminForm;
use App\Filament\Resources\Admins\Schemas\AdminInfolist;
use App\Filament\Resources\Admins\Tables\AdminsTable;
use App\Filament\Support\GuardRails;
use App\Models\Admin;
use App\Repositories\Contracts\AdminRepository;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class AdminResource extends Resource
{
    protected static ?string $model = Admin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    #[Override]
    public static function getModelLabel(): string
    {
        return __('admin.admins.label');
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return __('admin.admins.plural');
    }

    #[Override]
    public static function getNavigationGroup(): string
    {
        return __('admin.navigation.access');
    }

    /**
     * Scoping the base query is what makes super admins invisible to other
     * admins everywhere at once: the table, record URLs typed by hand,
     * global search and relation pickers all start from this query.
     *
     * @return Builder<Admin>
     */
    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return resolve(AdminRepository::class)
            ->queryVisibleTo(GuardRails::actor())
            // The policy compares each row's permissions with the actor's.
            ->with(['permissions', 'roles.permissions']);
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return AdminForm::configure($schema);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return AdminInfolist::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return AdminsTable::configure($table);
    }

    #[Override]
    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListAdmins::route('/'),
            'create' => CreateAdmin::route('/create'),
            'view' => ViewAdmin::route('/{record}'),
            'edit' => EditAdmin::route('/{record}/edit'),
        ];
    }
}
