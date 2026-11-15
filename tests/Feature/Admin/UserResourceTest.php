<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\Facades\Hash;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    actingAsAdmin(superAdmin());
});

it('lists users', function (): void {
    $users = User::factory()->count(3)->create();

    livewire(ListUsers::class)->assertCanSeeTableRecords($users);
});

it('creates a user through the service', function (): void {
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Yusuf Karim',
            'email' => 'yusuf@example.com',
            'password' => 'A-very-strong-Passw0rd',
            'password_confirmation' => 'A-very-strong-Passw0rd',
            'email_verified' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'yusuf@example.com')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('A-very-strong-Passw0rd', $user->password))->toBeTrue();
});

it('can create a user who still has to verify their address', function (): void {
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Yusuf Karim',
            'email' => 'yusuf@example.com',
            'password' => 'A-very-strong-Passw0rd',
            'password_confirmation' => 'A-very-strong-Passw0rd',
            'email_verified' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'yusuf@example.com')->firstOrFail()->hasVerifiedEmail())->toBeFalse();
});

it('updates a user and can withdraw verification', function (): void {
    $user = User::factory()->create();

    livewire(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertFormSet(['email_verified' => true])
        ->fillForm(['name' => 'Renamed', 'email_verified' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->name)->toBe('Renamed')
        ->and($user->hasVerifiedEmail())->toBeFalse();
});

it('deletes a user together with their API tokens', function (): void {
    $user = User::factory()->create();
    $user->createToken('phone');

    livewire(ListUsers::class)->callTableAction(DeleteAction::class, $user);

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('bulk deletes users', function (): void {
    $users = User::factory()->count(3)->create();

    livewire(ListUsers::class)->callTableBulkAction(DeleteBulkAction::class, $users);

    expect(User::query()->count())->toBe(0);
});

it('does not offer bulk delete without the delete_any permission', function (): void {
    actingAsAdmin(adminWith(['view_any:user', 'delete:user']));
    User::factory()->create();

    livewire(ListUsers::class)->assertTableBulkActionHidden(DeleteBulkAction::class);
});
