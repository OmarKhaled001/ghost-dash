<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\SeoPage;
use App\Repositories\Contracts\SeoPageRepository;
use Illuminate\Database\Eloquent\Builder;

final class EloquentSeoPageRepository implements SeoPageRepository
{
    public function query(): Builder
    {
        return SeoPage::query();
    }

    public function find(int $id): ?SeoPage
    {
        return SeoPage::query()->find($id);
    }

    public function findByPath(string $path): ?SeoPage
    {
        return SeoPage::query()->where('path', $path)->first();
    }

    public function create(array $attributes): SeoPage
    {
        $seoPage = new SeoPage;
        $seoPage->forceFill($attributes)->save();

        return $seoPage->refresh();
    }

    public function update(SeoPage $seoPage, array $attributes): SeoPage
    {
        $seoPage->forceFill($attributes)->save();

        return $seoPage;
    }

    public function delete(SeoPage $seoPage): void
    {
        $seoPage->delete();
    }
}
