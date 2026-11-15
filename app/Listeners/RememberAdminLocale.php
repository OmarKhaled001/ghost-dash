<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Admin;
use App\Support\Locales;
use BezhanSalleh\LanguageSwitch\Events\LocaleChanged;
use Illuminate\Support\Facades\Auth;

final class RememberAdminLocale
{
    /**
     * The language switcher keeps the choice in the session and a cookie,
     * which are both tied to one browser. Storing it on the admin as well
     * makes the panel open in their language wherever they sign in.
     */
    public function handle(LocaleChanged $event): void
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin instanceof Admin || ! Locales::isSupported($event->locale)) {
            return;
        }

        // A display preference, not an edit of the account.
        $admin->timestamps = false;
        $admin->forceFill(['locale' => $event->locale])->saveQuietly();
        $admin->timestamps = true;
    }
}
