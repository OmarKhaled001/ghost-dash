<?php

declare(strict_types=1);

namespace App\Filament\Resources\SeoPages\Pages;

use App\Filament\Resources\SeoPages\SeoPageResource;
use App\Services\SeoService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

final class CreateSeoPage extends CreateRecord
{
    protected static string $resource = SeoPageResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        return resolve(SeoService::class)->create($data);
    }
}
