<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Exceptions\GuardRailViolation;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Support\GuardRails;
use App\Models\Role;
use App\Services\RoleService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

final class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            RoleResource::deleteAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Role $record */
        try {
            return resolve(RoleService::class)->update(
                GuardRails::actor(),
                $record,
                (string) $data['name'],
                RoleResource::permissionsFrom($data),
            );
        } catch (GuardRailViolation $violation) {
            GuardRails::halt($violation);
        }
    }
}
