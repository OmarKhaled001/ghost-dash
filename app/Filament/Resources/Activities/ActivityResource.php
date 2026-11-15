<?php

declare(strict_types=1);

namespace App\Filament\Resources\Activities;

use App\Enums\CustomPermission;
use App\Filament\Contracts\GatedByPermission;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use App\Filament\Support\GuardRails;
use App\Models\Activity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;

/**
 * Read-only by construction: there are no create or edit pages, the table
 * has no write actions, and ActivityPolicy denies every write.
 */
final class ActivityResource extends Resource implements GatedByPermission
{
    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'activity-log';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 1;

    public static function gatePermission(): string
    {
        return CustomPermission::ViewActivityLog->value;
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return __('admin.activity.label');
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return __('admin.activity.plural');
    }

    #[Override]
    public static function getNavigationGroup(): string
    {
        return __('admin.navigation.system');
    }

    /**
     * @return Builder<Activity>
     */
    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return Activity::query()->visibleTo(GuardRails::actor());
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return ActivitiesTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }
}
