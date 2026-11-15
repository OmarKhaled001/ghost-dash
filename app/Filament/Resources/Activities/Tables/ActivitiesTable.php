<?php

declare(strict_types=1);

namespace App\Filament\Resources\Activities\Tables;

use App\Filament\Resources\Activities\Schemas\ActivityInfolist;
use App\Filament\Support\GuardRails;
use App\Models\Activity;
use App\Support\ActivityPresenter;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

final class ActivitiesTable
{
    /**
     * @param  bool  $forOneRecord  True inside a record's timeline, where the
     *                              subject is always that record.
     */
    public static function configure(Table $table, bool $forOneRecord = false): Table
    {
        return $table
            // Causer and subject are polymorphic. Without this every row
            // would fetch them one by one. A record's timeline starts from
            // the record's relation rather than the resource's query, so it
            // has to apply the visibility scope itself.
            ->modifyQueryUsing(fn (Builder $query): Builder => self::prepare($query, $forOneRecord))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.activity.columns.when'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('log_name')
                    ->label(__('admin.activity.columns.log'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => ActivityPresenter::log($state))
                    ->hidden($forOneRecord),

                TextColumn::make('event')
                    ->label(__('admin.activity.columns.event'))
                    ->badge()
                    ->color(fn (?string $state): string => self::eventColor($state))
                    ->formatStateUsing(fn (?string $state): string => ActivityPresenter::event($state)),

                TextColumn::make('subject_id')
                    ->label(__('admin.activity.columns.subject'))
                    ->state(fn (Activity $record): string => ActivityPresenter::record($record->subject_type, $record->subject_id, $record->subject))
                    ->hidden($forOneRecord),

                TextColumn::make('causer_id')
                    ->label(__('admin.activity.columns.causer'))
                    ->state(fn (Activity $record): string => $record->causer_type === null
                        ? __('admin.activity.system')
                        : ActivityPresenter::record($record->causer_type, $record->causer_id, $record->causer)),

                TextColumn::make('properties.ip')
                    ->label(__('admin.activity.properties.ip'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters($forOneRecord ? [self::eventFilter(), self::dateFilter()] : [
                SelectFilter::make('log_name')
                    ->label(__('admin.activity.columns.log'))
                    ->options(ActivityPresenter::logOptions())
                    ->multiple(),

                self::eventFilter(),

                SelectFilter::make('causer_type')
                    ->label(__('admin.activity.filters.causer_type'))
                    ->options(ActivityPresenter::modelOptions()),

                SelectFilter::make('subject_type')
                    ->label(__('admin.activity.filters.subject_type'))
                    ->options(ActivityPresenter::modelOptions()),

                self::dateFilter(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->schema(fn ($schema): Schema => ActivityInfolist::configure($schema))
                    ->modalHeading(fn (Activity $record): string => ActivityPresenter::event($record->event))
                    ->slideOver(),
            ]);
    }

    private static function eventFilter(): SelectFilter
    {
        return SelectFilter::make('event')
            ->label(__('admin.activity.columns.event'))
            ->options(ActivityPresenter::eventOptions())
            ->multiple();
    }

    private static function dateFilter(): Filter
    {
        return Filter::make('created_at')
            ->schema([
                DatePicker::make('from')
                    ->label(__('admin.activity.filters.from')),
                DatePicker::make('until')
                    ->label(__('admin.activity.filters.until')),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '>=', Date::parse($date)->startOfDay()))
                ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->where('created_at', '<=', Date::parse($date)->endOfDay())))
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if (filled($data['from'] ?? null)) {
                    $indicators[] = Indicator::make(__('admin.activity.filters.from').': '.Date::parse($data['from'])->toFormattedDateString())
                        ->removeField('from');
                }

                if (filled($data['until'] ?? null)) {
                    $indicators[] = Indicator::make(__('admin.activity.filters.until').': '.Date::parse($data['until'])->toFormattedDateString())
                        ->removeField('until');
                }

                return $indicators;
            });
    }

    private static function eventColor(?string $event): string
    {
        return match ($event) {
            'created', 'login', 'registered', 'email_verified', 'mfa_enabled' => 'success',
            'deleted', 'failed_login', 'lockout', 'mfa_disabled' => 'danger',
            'updated', 'roles_synced', 'permissions_synced', 'password_reset' => 'warning',
            default => 'gray',
        };
    }

    /**
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    private static function prepare(Builder $query, bool $forOneRecord): Builder
    {
        $query->with(['causer', 'subject']);

        return $forOneRecord ? $query->visibleTo(GuardRails::actor()) : $query;
    }
}
