<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Filament\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use App\Repositories\Contracts\UserRepository;
use App\Services\UserService;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rules\Password;
use Override;

final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    #[Override]
    public static function getModelLabel(): string
    {
        return __('admin.users.label');
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return __('admin.users.plural');
    }

    #[Override]
    public static function getNavigationGroup(): string
    {
        return __('admin.navigation.people');
    }

    /**
     * @return Builder<User>
     */
    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return resolve(UserRepository::class)->query();
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('admin.fields.name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label(__('admin.fields.email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('password')
                            ->label(__('admin.fields.password'))
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->rule(Password::defaults())
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText(fn (string $operation): ?string => $operation === 'edit' ? __('admin.admins.hints.password_unchanged') : null)
                            ->same('password_confirmation'),

                        TextInput::make('password_confirmation')
                            ->label(__('admin.fields.password_confirmation'))
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(false),

                        Toggle::make('email_verified')
                            ->label(__('admin.fields.email_verified'))
                            ->helperText(__('admin.users.hints.email_verified'))
                            ->default(true)
                            ->afterStateHydrated(function (Toggle $component, ?User $record): void {
                                if ($record instanceof User) {
                                    $component->state($record->hasVerifiedEmail());
                                }
                            }),
                    ]),
            ]);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('admin.fields.name')),

                        TextEntry::make('email')
                            ->label(__('admin.fields.email'))
                            ->copyable(),

                        IconEntry::make('email_verified_at')
                            ->label(__('admin.fields.email_verified'))
                            ->state(fn (User $record): bool => $record->hasVerifiedEmail())
                            ->boolean(),

                        TextEntry::make('created_at')
                            ->label(__('admin.fields.created_at'))
                            ->dateTime(),
                    ]),
            ]);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.fields.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('admin.fields.email'))
                    ->searchable()
                    ->copyable(),

                IconColumn::make('email_verified_at')
                    ->label(__('admin.fields.email_verified'))
                    ->state(fn (User $record): bool => $record->hasVerifiedEmail())
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('email_verified_at')
                    ->label(__('admin.fields.email_verified'))
                    ->nullable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::deleteAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->using(function (Collection $records): void {
                            $records
                                ->whereInstanceOf(User::class)
                                ->each(fn (User $user) => resolve(UserService::class)->delete($user));
                        }),
                ]),
            ]);
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->using(function (User $record): bool {
                resolve(UserService::class)->delete($record);

                return true;
            });
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
