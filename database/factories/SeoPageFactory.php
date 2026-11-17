<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SeoPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoPage>
 */
final class SeoPageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => '/'.fake()->unique()->slug(2),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(12),
        ];
    }
}
