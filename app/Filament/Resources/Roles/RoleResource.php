<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles;

use App\Filament\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Pages\ViewRole;
use App\Filament\Support\GuardRails;
use App\Models\Role;
use App\Services\RoleService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasShieldFormComponents;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Override;

/**
 * Published from Filament Shield and trimmed to what GhostDash uses: one
 * guard, no tenancy, and every write routed through RoleService. The
 * permission tabs still come from Shield's HasShieldFormComponents.
 */
final class RoleResource extends Resource
{
    use HasShieldFormComponents;

    /**
     * Form keys that are not permission checkbox lists.
     */
    public const array NON_PERMISSION_FIELDS = ['name', 'guard_name', 'select_all'];

    protected static ?string $model = Role::class;

    protected static ?string $slug = 'shield/roles';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    #[Override]
    public static function getModelLabel(): string
    {
        return __('filament-shield::filament-shield.resource.label.role');
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return __('filament-shield::filament-shield.resource.label.roles');
    }

    #[Override]
    public static function getNavigationGroup(): string
    {
        return __('admin.navigation.access');
    }

    /**
     * @return Builder<Role>
     */
    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return Role::query()
            ->where('guard_name', Role::GUARD)
            ->unless(
                GuardRails::actor()->isSuperAdmin(),
                fn (Builder $query): Builder => $query->where('name', '!=', Role::SUPER_ADMIN),
            );
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament-shield::filament-shield.field.name'))
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('guard_name', Role::GUARD),
                            ),

                        self::getSelectAllFormComponent(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                self::getShieldFormComponents(),
            ]);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-shield::filament-shield.column.name'))
                    ->weight(FontWeight::Medium)
                    ->formatStateUsing(fn (string $state): string => Str::headline($state))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('permissions_count')
                    ->label(__('filament-shield::filament-shield.column.permissions'))
                    ->counts('permissions')
                    ->badge(),

                TextColumn::make('users_count')
                    ->label(__('admin.admins.plural'))
                    ->counts('users')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('updated_at')
                    ->label(__('filament-shield::filament-shield.column.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::deleteAction(),
            ]);
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->using(fn (Role $record): bool => GuardRails::attempt(
                fn () => resolve(RoleService::class)->delete($record),
            ));
    }

    /**
     * Shield's form posts one checkbox list per resource, page, widget and
     * custom-permission tab. Everything that is not a known plain field is
     * therefore a list of permission names.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function permissionsFrom(array $data): array
    {
        return collect($data)
            ->except(self::NON_PERMISSION_FIELDS)
            ->flatten()
            ->filter(fn (mixed $permission): bool => is_string($permission))
            ->unique()
            ->values()
            ->all();
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
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'view' => ViewRole::route('/{record}'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
