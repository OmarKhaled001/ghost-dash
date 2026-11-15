<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityLog;
use App\Enums\AuthActivity;
use App\Support\AuthActivityLogger;
use Carbon\CarbonImmutable;
use Database\Factories\AdminFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\Email\Concerns\InteractsWithEmailAuthentication;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Notifications\VerifyEmail as VerifyEmailNotification;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Override;
use SensitiveParameter;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $avatar_path
 * @property bool $is_active
 * @property string|null $locale
 * @property CarbonImmutable|null $last_login_at
 * @property string|null $last_login_ip
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'avatar_path', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
final class Admin extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar, HasEmailAuthentication, MustVerifyEmail
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory;

    use HasRoles {
        syncRoles as private syncRolesWithoutLogging;
    }
    use InteractsWithAppAuthentication;
    use InteractsWithAppAuthenticationRecovery;
    use InteractsWithEmailAuthentication;
    use LogsActivity;
    use Notifiable;

    public const string PANEL = 'admin';

    /**
     * Defaults for a new instance, mirroring the column defaults. Without
     * them a model that was just created in memory has no value at all for
     * these keys, and reading one is an error under strict mode.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'avatar_path' => null,
        'is_active' => true,
        'email_verified_at' => null,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        // password is listed so that a password change shows up in the log.
        // Its value never does: RedactedLogActivityAction masks it.
        return LogOptions::defaults()
            ->useLogName(ActivityLog::Admins)
            ->logOnly(['name', 'email', 'password', 'avatar_path', 'is_active', 'email_verified_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Role changes live in a pivot table, so no model event fires for them.
     * Wrapping the sync records every change of an admin's access, whoever
     * makes it: the panel, the installer or a console command.
     *
     * @param  mixed  ...$roles
     */
    public function syncRoles(...$roles): static
    {
        $before = $this->exists ? $this->roles()->pluck('name')->all() : [];

        $this->syncRolesWithoutLogging(...$roles);

        $after = $this->roles()->pluck('name')->all();
        $attached = array_values(array_diff($after, $before));
        $detached = array_values(array_diff($before, $after));

        if ($attached !== [] || $detached !== []) {
            activity(ActivityLog::Admins)
                ->performedOn($this)
                ->event('roles_synced')
                ->withProperties(['attached' => $attached, 'detached' => $detached])
                ->log('roles_synced');
        }

        return $this;
    }

    /**
     * An admin without a role has no permissions at all, so there is nothing
     * in the panel they could use. Requiring a role also means a freshly
     * inserted row cannot sign in until someone deliberately grants access.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->roles->isNotEmpty();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    /**
     * Whether this admin holds every permission the role carries. Handing
     * out a role is handing out its permissions, so "may edit admins" must
     * not reach further than what the editor can already do.
     */
    public function mayGrant(Role $role): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($role->isSuperAdmin()) {
            return false;
        }

        return $role->permissions->pluck('name')
            ->diff($this->getAllPermissions()->pluck('name'))
            ->isEmpty();
    }

    /**
     * Whether this admin holds every permission the other one has. Editing
     * someone who can do more (setting their password, say) would be a way
     * to borrow their access.
     */
    public function hasAuthorityOver(self $other): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($other->isSuperAdmin()) {
            return false;
        }

        return $other->loadMissing('permissions')->getAllPermissions()->pluck('name')
            ->diff($this->getAllPermissions()->pluck('name'))
            ->isEmpty();
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_path === null
            ? null
            : Storage::disk('public')->url($this->avatar_path);
    }

    /**
     * Laravel's default notification links to a "verification.verify" route
     * that this application does not have. Admins verify inside the panel.
     */
    public function sendEmailVerificationNotification(): void
    {
        $notification = resolve(VerifyEmailNotification::class);
        $notification->url = Filament::getPanel(self::PANEL)->getVerifyEmailUrl($this);

        $this->notify($notification);
    }

    /**
     * Same reason as above: the reset form lives in the panel, not on a
     * "password.reset" route.
     */
    public function sendPasswordResetNotification(#[SensitiveParameter] $token): void
    {
        $notification = resolve(ResetPasswordNotification::class, ['token' => $token]);
        $notification->url = Filament::getPanel(self::PANEL)->getResetPasswordUrl($token, $this);

        $this->notify($notification);
    }

    #[Override]
    protected static function booted(): void
    {
        // Filament toggles multi-factor authentication by saving these
        // columns and raises no event of its own.
        self::updated(function (Admin $admin): void {
            $admin->logMultiFactorChange('app', 'app_authentication_secret');
            $admin->logMultiFactorChange('email', 'has_email_authentication');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    private function logMultiFactorChange(string $provider, string $column): void
    {
        if (! $this->wasChanged($column)) {
            return;
        }

        // Raw values on purpose: one column is a boolean stored as 0/1, the
        // other is ciphertext or null, and "truthy" means enabled for both.
        $wasEnabled = (bool) $this->getRawOriginal($column);
        $isEnabled = (bool) ($this->getAttributes()[$column] ?? null);

        if ($wasEnabled === $isEnabled) {
            return;
        }

        resolve(AuthActivityLogger::class)->log(
            $isEnabled ? AuthActivity::MfaEnabled : AuthActivity::MfaDisabled,
            $this,
            properties: ['provider' => $provider],
        );
    }
}
