<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('attributes changes made in an admin session to the admin', function (): void {
    $admin = actingAsAdmin();
    $user = User::factory()->create();

    $user->update(['name' => 'Changed by an admin']);

    expect(Activity::query()->where('event', 'updated')->latest('id')->first())
        ->causer_type->toBe('admin')
        ->causer_id->toBe($admin->id);
});

it('attributes changes made with a Sanctum token to the user', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $user->update(['name' => 'Changed through the API']);

    expect(Activity::query()->where('event', 'updated')->latest('id')->first())
        ->causer_type->toBe('user')
        ->causer_id->toBe($user->id);
});

it('attributes changes made in a web session to the user', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user, 'web');

    $user->update(['name' => 'Changed in a web session']);

    expect(Activity::query()->where('event', 'updated')->latest('id')->first())
        ->causer_type->toBe('user')
        ->causer_id->toBe($user->id);
});

it('prefers the admin when a request somehow carries both', function (): void {
    $admin = actingAsAdmin();
    $user = User::factory()->create();
    $this->actingAs($user, 'web');
    $this->actingAs($admin, 'admin');

    $user->update(['name' => 'Changed']);

    expect(Activity::query()->where('event', 'updated')->latest('id')->first()->causer_type)->toBe('admin');
});

it('leaves the causer empty when nobody is signed in', function (): void {
    $user = User::factory()->create();

    $user->update(['name' => 'Changed by a console command']);

    expect(Activity::query()->where('event', 'updated')->latest('id')->first())
        ->causer_type->toBeNull()
        ->causer_id->toBeNull();
});

it('lets an explicit causer win over the signed-in one', function (): void {
    actingAsAdmin();
    $user = User::factory()->create();

    activity()->causedBy($user)->log('explicit');

    expect(Activity::query()->latest('id')->first())
        ->causer_type->toBe('user')
        ->causer_id->toBe($user->id);
});
