<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Exceptions\GuardRailViolation;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Support\GuardRails;
use App\Services\RoleService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

final class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return resolve(RoleService::class)->create(
                GuardRails::actor(),
                (string) $data['name'],
                RoleResource::permissionsFrom($data),
            );
        } catch (GuardRailViolation $violation) {
            GuardRails::halt($violation);
        }
    }
}
