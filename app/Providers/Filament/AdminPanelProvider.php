<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Enums\CustomPermission;
use App\Filament\Pages\Auth\Login;
use App\Filament\Support\InitialsAvatarProvider;
use App\Http\Middleware\ApplyGeneralSettings;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Settings\AppearanceSettings;
use App\Settings\GeneralSettings;
use App\Support\Locales;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class AdminPanelProvider extends PanelProvider
{
    private const string LOCALE_COOKIE = 'ghostdash_locale';

    public function boot(): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch): void {
            $switch
                ->locales(Locales::supported())
                // Each language in its own name and script, so it can be
                // found by someone who cannot read the current language.
                ->nativeLabel()
                // On the login page too: a visitor should not have to sign in
                // through a language they do not read.
                ->visible(outsidePanels: true)
                ->cookieName(self::LOCALE_COOKIE)
                // The switcher asks, in order: this session, then this
                // callback, then the browser's Accept-Language header. By
                // always answering here, the order becomes: the admin's own
                // saved choice, the choice remembered by this browser, then
                // the default from General settings. The browser language is
                // deliberately not consulted: an admin-configured default
                // should not lose to whatever language the OS shipped in.
                ->userPreferredLocale(fn (): string => Auth::guard('admin')->user()->locale
                    ?? request()->cookie(self::LOCALE_COOKIE)
                    ?? config('app.locale'));
        });
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path(config('ghostdash.panel.path'))
            ->authGuard('admin')
            ->authPasswordBroker('admins')
            ->login(Login::class)
            ->passwordReset()
            ->emailVerification()
            ->emailChangeVerification()
            ->profile(isSimple: false)
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
                EmailAuthentication::make(),
            ])
            // A resource without a policy is a bug, not a public resource.
            ->strictAuthorization()
            // Closures, because the values come from the database and this
            // provider also boots for commands that run before one exists.
            ->brandName(fn (): string => resolve(GeneralSettings::class)->app_name)
            ->brandLogo(fn (): ?string => $this->publicUrl(resolve(GeneralSettings::class)->logo_path))
            ->favicon(fn (): ?string => $this->publicUrl(resolve(GeneralSettings::class)->favicon_path))
            // Read when the panel boots for a request, so a change on the
            // Appearance tab shows on the next page load.
            ->colors(fn (): array => [
                'primary' => resolve(AppearanceSettings::class)->primaryColor()->palette(),
                'gray' => Color::Slate,
            ])
            // Plex Sans Arabic carries matching Latin and Arabic glyphs, so
            // the panel looks like one typeface in both languages.
            ->font('IBM Plex Sans Arabic')
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->navigationItems([
                NavigationItem::make('Telescope')
                    ->url(fn (): string => url((string) config('telescope.path')), shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedMagnifyingGlassCircle)
                    ->group(fn (): string => __('admin.navigation.system'))
                    ->sort(90)
                    // Hidden while Telescope is switched off, when the link
                    // would lead to a 404.
                    ->visible(fn (): bool => (bool) config('telescope.enabled')
                        && (Auth::guard('admin')->user()?->can(CustomPermission::ViewTelescope->value) ?? false)),
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->localizePermissionLabels(),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                // Before the language switcher's middleware (which Filament
                // places next to DispatchServingFilamentEvent): this sets the
                // default locale, the switcher then applies the admin's own.
                ApplyGeneralSettings::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Livewire calls skip the panel middleware unless it is marked
            // persistent, and they need the display time zone as well.
            ->persistentMiddleware([
                ApplyGeneralSettings::class,
            ])
            // EnsureAdminIsActive must run first: it signs a deactivated admin
            // out, and Authenticate then redirects them to the login page
            // like any other guest instead of answering 403.
            ->authMiddleware([
                EnsureAdminIsActive::class,
                Authenticate::class,
            ], isPersistent: true);
    }

    private function publicUrl(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('public')->url($path);
    }
}
