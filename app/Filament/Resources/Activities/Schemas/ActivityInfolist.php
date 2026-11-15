<?php

declare(strict_types=1);

namespace App\Filament\Resources\Activities\Schemas;

use App\Models\Activity;
use App\Support\ActivityPresenter;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ActivityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label(__('admin.activity.columns.when'))
                            ->dateTime(),

                        TextEntry::make('event')
                            ->label(__('admin.activity.columns.event'))
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => ActivityPresenter::event($state)),

                        TextEntry::make('subject_id')
                            ->label(__('admin.activity.columns.subject'))
                            ->state(fn (Activity $record): string => ActivityPresenter::record($record->subject_type, $record->subject_id, $record->subject)),

                        TextEntry::make('causer_id')
                            ->label(__('admin.activity.columns.causer'))
                            ->state(fn (Activity $record): string => $record->causer_type === null
                                ? __('admin.activity.system')
                                : ActivityPresenter::record($record->causer_type, $record->causer_id, $record->causer)),
                    ]),

                Section::make(__('admin.activity.sections.changes'))
                    ->visible(fn (Activity $record): bool => ActivityPresenter::diff($record) !== [])
                    ->schema([
                        ViewEntry::make('attribute_changes')
                            ->hiddenLabel()
                            ->view('filament.activities.diff')
                            ->viewData(fn (Activity $record): array => ['rows' => ActivityPresenter::diff($record)]),
                    ]),

                Section::make(__('admin.activity.sections.details'))
                    ->visible(fn (Activity $record): bool => ActivityPresenter::properties($record) !== [])
                    ->schema([
                        KeyValueEntry::make('properties')
                            ->hiddenLabel()
                            ->keyLabel(__('admin.activity.columns.property'))
                            ->valueLabel(__('admin.activity.columns.value'))
                            ->state(fn (Activity $record): array => ActivityPresenter::properties($record)),
                    ]),
            ]);
    }
}
