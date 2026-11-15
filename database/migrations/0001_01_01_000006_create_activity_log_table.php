<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');

            // Polymorphic on purpose, and without foreign keys: the log must
            // outlive the records it describes ("who deleted admin 12?").
            // nullableMorphs() adds the (type, id) composite index for each.
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable()->index();
            $table->nullableMorphs('causer', 'causer');

            // JSON because the shape differs per event: an attribute diff for
            // model changes, IP and user agent for sign-ins.
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            // The panel lists newest first and filters by date range, and the
            // nightly clean-up deletes by age.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
