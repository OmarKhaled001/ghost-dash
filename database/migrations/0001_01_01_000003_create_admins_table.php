<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar_path')->nullable();
            $table->boolean('is_active')->default(true)->index();

            // The panel language this admin picked, so it follows them across
            // browsers. Null means "use the application default".
            $table->string('locale', 8)->nullable();

            // Filament multi-factor authentication. Both secrets are stored
            // encrypted, which is why they are TEXT rather than VARCHAR.
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            $table->boolean('has_email_authentication')->default(false);

            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Admins get their own reset-token table so that a reset requested
        // for a user can never be redeemed against an admin with the same
        // email address, or the other way round.
        Schema::create('admin_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_password_reset_tokens');
        Schema::dropIfExists('admins');
    }
};
