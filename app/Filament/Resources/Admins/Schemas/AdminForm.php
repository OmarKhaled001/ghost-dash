<?php

declare(strict_types=1);

namespace App\Filament\Resources\Admins\Schemas;

use App\Filament\Support\GuardRails;
use App\Filament\Support\RasterImage;
use App\Models\Admin;
use App\Models\Role;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class AdminForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.admins.sections.account'))
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

                        FileUpload::make('avatar_path')
                            ->label(__('admin.fields.avatar'))
                            ->avatar()
                            ->image()
                            ->acceptedFileTypes(RasterImage::MIME_TYPES)
                            // Besides a fresh upload, only the path already
                            // on the record is accepted: the browser cannot
                            // point this column at some other stored file.
                            ->preventFilePathTampering()
                            ->disk('public')
                            ->directory('avatars')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('admin.admins.sections.access'))
                    ->columns(2)
                    ->schema([
                        Select::make('roles')
                            ->label(__('admin.fields.roles'))
                            ->multiple()
                            ->required()
                            ->options(fn (): array => self::assignableRoles())
                            ->helperText(__('admin.admins.hints.roles')),

                        Toggle::make('is_active')
                            ->label(__('admin.fields.is_active'))
                            ->helperText(__('admin.admins.hints.is_active'))
                            ->default(true)
                            // The service refuses this anyway; disabling the
                            // switch just saves the admin from finding out.
                            ->disabled(fn (?Admin $record): bool => $record?->is(GuardRails::actor()) ?? false)
                            ->inline(false),
                    ]),
            ]);
    }

    /**
     * Roles the acting admin is allowed to hand out: those that carry no
     * permission the admin lacks, which leaves super_admin to super admins.
     * AdminService enforces the same rule on save.
     *
     * @return array<string, string>
     */
    private static function assignableRoles(): array
    {
        $actor = GuardRails::actor();

        return Role::query()
            ->where('guard_name', Role::GUARD)
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role): bool => $actor->mayGrant($role))
            ->mapWithKeys(fn (Role $role): array => [$role->name => Str::headline($role->name)])
            ->all();
    }
}
