<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityLog;
use App\Notifications\Api\ResetPasswordNotification;
use App\Notifications\Api\VerifyEmailNotification;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use SensitiveParameter;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
final class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use LogsActivity;
    use Notifiable;

    /**
     * See Admin::$attributes for why the default is spelled out.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'email_verified_at' => null,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        // password is listed so that a password change shows up in the log.
        // Its value never does: RedactedLogActivityAction masks it.
        return LogOptions::defaults()
            ->useLogName(ActivityLog::Users)
            ->logOnly(['name', 'email', 'password', 'email_verified_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Laravel's default links to a "verification.verify" web route. Users of
     * this application verify through the API.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    /**
     * Laravel's default links to a "password.reset" web route. The reset
     * form belongs to the frontend at FRONTEND_URL.
     */
    public function sendPasswordResetNotification(#[SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }
}
