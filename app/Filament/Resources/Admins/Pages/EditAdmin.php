<?php

declare(strict_types=1);

namespace App\Filament\Resources\Admins\Pages;

use App\Exceptions\GuardRailViolation;
use App\Filament\Resources\Admins\AdminResource;
use App\Filament\Resources\Admins\Tables\AdminsTable;
use App\Filament\Support\GuardRails;
use App\Models\Admin;
use App\Services\AdminService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

final class EditAdmin extends EditRecord
{
    protected static string $resource = AdminResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            AdminsTable::deactivateAction(),
            AdminsTable::activateAction(),
            AdminsTable::deleteAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Admin $admin */
        $admin = $this->getRecord();

        // Roles are a plain select rather than a relationship field, so that
        // saving them goes through AdminService and its guard rails.
        $data['roles'] = $admin->roles->pluck('name')->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Admin $record */
        try {
            return resolve(AdminService::class)->update(
                GuardRails::actor(),
                $record,
                $data,
                array_values($data['roles'] ?? []),
            );
        } catch (GuardRailViolation $violation) {
            GuardRails::halt($violation);
        }
    }
}
