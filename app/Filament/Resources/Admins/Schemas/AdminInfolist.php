<?php

declare(strict_types=1);

namespace App\Filament\Resources\Admins\Schemas;

use App\Filament\Support\InitialsAvatarProvider;
use App\Models\Admin;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class AdminInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.admins.sections.account'))
                    ->columns(3)
                    ->schema([
                        ImageEntry::make('avatar_path')
                            ->label(__('admin.fields.avatar'))
                            ->disk('public')
                            ->circular()
                            ->defaultImageUrl(fn (Admin $record): string => InitialsAvatarProvider::for($record->name)),

                        TextEntry::make('name')
                            ->label(__('admin.fields.name')),

                        TextEntry::make('email')
                            ->label(__('admin.fields.email'))
                            ->copyable(),
                    ]),

                Section::make(__('admin.admins.sections.access'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('roles.name')
                            ->label(__('admin.fields.roles'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => Str::headline($state)),

                        IconEntry::make('is_active')
                            ->label(__('admin.fields.is_active'))
                            ->boolean(),

                        IconEntry::make('email_verified_at')
                            ->label(__('admin.fields.email_verified'))
                            ->state(fn (Admin $record): bool => $record->hasVerifiedEmail())
                            ->boolean(),

                        IconEntry::make('has_app_authentication')
                            ->label(__('admin.fields.app_authentication'))
                            ->state(fn (Admin $record): bool => filled($record->getAppAuthenticationSecret()))
                            ->boolean(),

                        IconEntry::make('has_email_authentication')
                            ->label(__('admin.fields.email_authentication'))
                            ->boolean(),
                    ]),

                Section::make(__('admin.admins.sections.activity'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('last_login_at')
                            ->label(__('admin.fields.last_login_at'))
                            ->dateTime()
                            ->placeholder(__('admin.admins.never_signed_in')),

                        TextEntry::make('last_login_ip')
                            ->label(__('admin.fields.last_login_ip'))
                            ->placeholder('—'),

                        TextEntry::make('created_at')
                            ->label(__('admin.fields.created_at'))
                            ->dateTime(),
                    ]),
            ]);
    }
}
