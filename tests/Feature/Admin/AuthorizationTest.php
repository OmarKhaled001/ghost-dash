<?php

declare(strict_types=1);

use App\Models\Admin;
use Tests\Support\Panel;

use function Pest\Laravel\get;

it('keeps the list of discovered panel entities non-empty', function (): void {
    // If discovery ever returned nothing, the datasets below would be empty
    // and the suite would pass while testing nothing at all.
    expect(Panel::entities()['resources'])->not->toBeEmpty()
        ->and(Panel::entities()['pages'])->not->toBeEmpty();
});

it('answers 403 on a resource without its permission and 200 with it', function (string $resource): void {
    $permission = Panel::permissionForResource($resource);

    actingAsAdmin(adminWith([]));
    get($resource::getUrl('index', panel: Admin::PANEL))->assertForbidden();

    actingAsAdmin(adminWith([$permission]));
    get($resource::getUrl('index', panel: Admin::PANEL))->assertOk();
})->with('panel resources');

it('answers 403 on a page without its permission and 200 with it', function (string $page): void {
    $permission = Panel::permissionForPage($page);

    actingAsAdmin(adminWith([]));
    get($page::getUrl(panel: Admin::PANEL))->assertForbidden();

    actingAsAdmin(adminWith([$permission]));
    get($page::getUrl(panel: Admin::PANEL))->assertOk();
})->with('gated panel pages');

it('redirects guests away from every page', function (string $page): void {
    get($page::getUrl(panel: Admin::PANEL))->assertRedirect(panelUrl('login'));
})->with('gated panel pages');

it('redirects guests away from every resource', function (string $resource): void {
    get($resource::getUrl('index', panel: Admin::PANEL))->assertRedirect(panelUrl('login'));
})->with('panel resources');

it('lets any admin who can enter the panel open the dashboard', function (): void {
    actingAsAdmin(adminWith([]));

    get(panelUrl())->assertOk();
});

it('refuses the panel to an admin without any role', function (): void {
    actingAsAdmin(admin(role: null));

    get(panelUrl())->assertForbidden();
});

it('hides navigation entries the admin has no permission for', function (): void {
    actingAsAdmin(adminWith(['view_any:user']));

    get(panelUrl())
        ->assertOk()
        ->assertSee(__('admin.users.plural'))
        ->assertDontSee(__('admin.admins.plural'));
});
