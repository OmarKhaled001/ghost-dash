<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_pages', function (Blueprint $table): void {
            $table->id();
            // Normalised: a leading slash, no trailing one, no query string.
            // Looked up on every page view, hence unique and indexed.
            $table->string('path', 255)->unique();
            $table->string('title', 255)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('keywords', 255)->nullable();
            $table->string('og_image_path')->nullable();
            $table->string('canonical_url', 2048)->nullable();
            // Null means "as the site-wide setting says".
            $table->boolean('robots_index')->nullable();
            $table->boolean('robots_follow')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_pages');
    }
};
