<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use App\Models\Admin;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

it('renders the login page', function (): void {
    get(panelUrl('login'))->assertOk();
});

it('redirects guests from the panel to the login page', function (): void {
    get(panelUrl())->assertRedirect(panelUrl('login'));
});

it('signs an admin in with valid credentials', function (): void {
    $admin = admin(['email' => 'omar@example.com']);

    livewire(Login::class)
        ->fillForm(['email' => 'omar@example.com', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getUrl());

    assertAuthenticatedAs($admin, 'admin');
});

it('records when and from where an admin last signed in', function (): void {
    $admin = admin();
    $updatedAt = $admin->updated_at;

    $this->travel(5)->minutes();

    livewire(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'password'])
        ->call('authenticate');

    $admin->refresh();

    expect($admin->last_login_at)->not->toBeNull()
        ->and($admin->last_login_ip)->toBe('127.0.0.1')
        // Signing in is not an edit of the admin record.
        ->and($admin->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('rejects a wrong password', function (): void {
    $admin = admin();

    livewire(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'not-the-password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    assertGuest('admin');
});

it('does not let an end user sign in to the panel', function (): void {
    $user = User::factory()->create();

    livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    assertGuest('admin');
    assertGuest('web');
});

it('throttles repeated login attempts and raises a lockout', function (): void {
    Event::fake([Lockout::class]);

    $admin = admin();
    $component = livewire(Login::class);

    foreach (range(1, 5) as $attempt) {
        $component
            ->fillForm(['email' => $admin->email, 'password' => 'wrong'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
    }

    // The sixth attempt is refused before the credentials are even checked,
    // so it stays refused when the password is finally right.
    $component
        ->fillForm(['email' => $admin->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertNotified();

    assertGuest('admin');
    Event::assertDispatched(Lockout::class);
});

it('refuses to sign in an inactive admin', function (): void {
    $admin = Admin::factory()->inactive()->create();

    livewire(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    assertGuest('admin');
});

it('signs out an admin who is deactivated while logged in', function (): void {
    $admin = actingAsAdmin();

    get(panelUrl())->assertOk();

    $admin->forceFill(['is_active' => false])->save();

    get(panelUrl())->assertRedirect(panelUrl('login'));

    assertGuest('admin');
});

it('signs an admin out', function (): void {
    actingAsAdmin();

    $this->post(panelUrl('logout'))->assertRedirect();

    assertGuest('admin');
});
