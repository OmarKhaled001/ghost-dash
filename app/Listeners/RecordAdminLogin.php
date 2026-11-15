<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Admin;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

final readonly class RecordAdminLogin
{
    public function __construct(private Request $request) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof Admin) {
            return;
        }

        // This is bookkeeping, not an edit of the admin: it must not bump
        // updated_at or show up as an "updated" entry in the activity log.
        $event->user->timestamps = false;

        $event->user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $this->request->ip(),
        ])->saveQuietly();

        $event->user->timestamps = true;
    }
}
