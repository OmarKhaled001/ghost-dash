<?php

declare(strict_types=1);

namespace App\Filament\RelationManagers;

use App\Enums\CustomPermission;
use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * A record's own history. Add it to getRelations() of any resource whose
 * model uses the LogsActivity trait:
 *
 *     return [ActivitiesRelationManager::class];
 */
final class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activitiesAsSubject';

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.activity.timeline');
    }

    /**
     * Being allowed to see a record does not include seeing who changed it.
     */
    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Filament::auth()->user()?->can(CustomPermission::ViewActivityLog->value) ?? false;
    }

    #[Override]
    public function isReadOnly(): bool
    {
        return true;
    }

    #[Override]
    public function table(Table $table): Table
    {
        return ActivitiesTable::configure($table, forOneRecord: true);
    }
}
