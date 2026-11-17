<?php

declare(strict_types=1);

namespace App\Filament\Resources\SeoPages\Pages;

use App\Filament\Resources\SeoPages\SeoPageResource;
use App\Models\SeoPage;
use App\Services\SeoService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

final class EditSeoPage extends EditRecord
{
    protected static string $resource = SeoPageResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            SeoPageResource::deleteAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var SeoPage $record */
        return resolve(SeoService::class)->update($record, $data);
    }
}
