<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\SeoPage;
use Illuminate\Database\Eloquent\Builder;

interface SeoPageRepository
{
    /**
     * @return Builder<SeoPage>
     */
    public function query(): Builder;

    public function find(int $id): ?SeoPage;

    public function findByPath(string $path): ?SeoPage;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): SeoPage;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(SeoPage $seoPage, array $attributes): SeoPage;

    public function delete(SeoPage $seoPage): void;
}
