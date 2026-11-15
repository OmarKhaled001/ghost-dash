<?php

declare(strict_types=1);

namespace App\Filament\Resources\Admins\Pages;

use App\Exceptions\GuardRailViolation;
use App\Filament\Resources\Admins\AdminResource;
use App\Filament\Support\GuardRails;
use App\Services\AdminService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

final class CreateAdmin extends CreateRecord
{
    protected static string $resource = AdminResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return resolve(AdminService::class)->create(
                GuardRails::actor(),
                $data,
                array_values($data['roles'] ?? []),
            );
        } catch (GuardRailViolation $violation) {
            GuardRails::halt($violation);
        }
    }
}
