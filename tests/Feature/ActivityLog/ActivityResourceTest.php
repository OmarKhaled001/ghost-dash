<?php

declare(strict_types=1);

use App\Filament\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Admins\AdminResource;
use App\Filament\Resources\Admins\Pages\EditAdmin;
use App\Filament\Resources\Admins\Pages\ViewAdmin;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\User;
use App\Support\ActivityPresenter;
use Filament\Actions\ViewAction;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->viewer = actingAsAdmin(adminWith(['view_activity_log']));
});

it('lists activity newest first', function (): void {
    $user = User::factory()->create();
    $user->update(['name' => 'Second']);

    $created = Activity::query()->where('event', 'created')->where('subject_type', 'user')->first();
    $updated = Activity::query()->where('event', 'updated')->where('subject_type', 'user')->first();

    livewire(ListActivities::class)
        ->assertCanSeeTableRecords([$updated, $created], inOrder: true);
});

it('filters by log name, event, causer type and subject type', function (): void {
    $user = User::factory()->create();
    $admin = Admin::factory()->create();
    // An edit the user made themselves, as the API would record it.
    activity('users')->performedOn($user)->causedBy($user)->event('updated')->log('updated');

    $userCreated = Activity::query()->where('subject_type', 'user')->where('event', 'created')->first();
    $userUpdated = Activity::query()->where('subject_type', 'user')->where('event', 'updated')->first();
    $adminCreated = Activity::query()->where('subject_type', 'admin')->where('subject_id', $admin->id)->where('event', 'created')->first();

    livewire(ListActivities::class)
        ->filterTable('log_name', ['users'])
        ->assertCanSeeTableRecords([$userCreated, $userUpdated])
        ->assertCanNotSeeTableRecords([$adminCreated])
        ->resetTableFilters()
        ->filterTable('event', ['updated'])
        ->assertCanSeeTableRecords([$userUpdated])
        ->assertCanNotSeeTableRecords([$userCreated, $adminCreated])
        ->resetTableFilters()
        ->filterTable('causer_type', 'user')
        ->assertCanSeeTableRecords([$userUpdated])
        ->assertCanNotSeeTableRecords([$userCreated, $adminCreated])
        ->resetTableFilters()
        ->filterTable('subject_type', 'admin')
        ->assertCanSeeTableRecords([$adminCreated])
        ->assertCanNotSeeTableRecords([$userCreated, $userUpdated]);
});

it('filters by date range', function (): void {
    $old = activity()->createdAt(now()->subDays(10))->log('old entry');
    $recent = activity()->createdAt(now()->subDay())->log('recent entry');

    livewire(ListActivities::class)
        ->filterTable('created_at', ['from' => now()->subDays(3)->toDateString(), 'until' => now()->toDateString()])
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old])
        ->resetTableFilters()
        ->filterTable('created_at', ['until' => now()->subDays(5)->toDateString()])
        ->assertCanSeeTableRecords([$old])
        ->assertCanNotSeeTableRecords([$recent]);
});

it('shows a before and after diff with translated field labels', function (): void {
    $user = User::factory()->create(['name' => 'Old Name']);
    $user->update(['name' => 'New Name']);
    $activity = Activity::query()->where('event', 'updated')->latest('id')->first();

    expect(ActivityPresenter::diff($activity))->toBe([
        ['label' => __('admin.fields.name'), 'old' => 'Old Name', 'new' => 'New Name'],
    ]);

    livewire(ListActivities::class)
        ->mountTableAction(ViewAction::class, $activity)
        ->assertMountedActionModalSee([
            __('admin.activity.diff.old'),
            __('admin.activity.diff.new'),
            __('admin.fields.name'),
            'Old Name',
            'New Name',
        ]);
});

it('translates the diff labels into Arabic', function (): void {
    $user = User::factory()->create(['name' => 'Old Name']);
    $user->update(['name' => 'New Name']);
    $activity = Activity::query()->where('event', 'updated')->latest('id')->first();

    app()->setLocale('ar');

    expect(ActivityPresenter::diff($activity)[0]['label'])->toBe('الاسم')
        ->and(ActivityPresenter::event('updated'))->toBe('تعديل');
});

it('is read-only for everyone, super admins included', function (): void {
    $super = superAdmin();
    $activity = activity()->log('entry');

    expect($super->can('viewAny', Activity::class))->toBeTrue()
        ->and($super->can('create', Activity::class))->toBeFalse()
        ->and($super->can('update', $activity))->toBeFalse()
        ->and($super->can('delete', $activity))->toBeFalse()
        ->and(array_keys(ActivityResource::getPages()))->toBe(['index']);
});

it('does not run a query per row for causers and subjects', function (): void {
    $queries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        livewire(ListActivities::class)->assertOk();

        return count(DB::getQueryLog());
    };

    // Fill the first page with rows of one kind, so that both measurements
    // render ten rows whose causers and subjects are of the same types.
    User::factory()->count(12)->create();
    $queries();
    $onePage = $queries();

    User::factory()->count(30)->create();
    $manyPages = $queries();

    expect($manyPages)->toBe($onePage);
});

it('keeps showing an entry after its record was deleted', function (): void {
    $user = User::factory()->create();
    $id = $user->id;
    $user->delete();

    $deleted = Activity::query()->where('event', 'deleted')->latest('id')->first();

    livewire(ListActivities::class)
        ->assertCanSeeTableRecords([$deleted])
        ->assertSee(ActivityPresenter::model('user').' #'.$id);
});

describe('super admins stay invisible in the log', function (): void {
    beforeEach(function (): void {
        $this->boss = superAdmin(['name' => 'Hidden Boss']);
        $this->user = User::factory()->create();

        // Done by the super admin, to an ordinary record.
        test()->actingAs($this->boss, 'admin');
        $this->user->update(['name' => 'Renamed By Boss']);
        $this->byBoss = Activity::query()->where('event', 'updated')->where('subject_type', 'user')->latest('id')->firstOrFail();

        // Done to the super admin.
        $this->boss->update(['name' => 'Still Hidden']);
        $this->aboutBoss = Activity::query()->where('subject_type', 'admin')->where('subject_id', $this->boss->id)->latest('id')->firstOrFail();

        // Done by somebody else, to an ordinary record.
        $editor = admin();
        test()->actingAs($editor, 'admin');
        $this->user->update(['name' => 'Renamed By Editor']);
        $this->byEditor = Activity::query()->where('causer_id', $editor->id)->where('subject_type', 'user')->latest('id')->firstOrFail();
    });

    it('leaves out what a super admin did, and what was done to one, for other admins', function (): void {
        actingAsAdmin($this->viewer);

        livewire(ListActivities::class)
            ->assertCanSeeTableRecords([$this->byEditor])
            ->assertCanNotSeeTableRecords([$this->byBoss, $this->aboutBoss])
            ->assertDontSee('Hidden Boss')
            ->assertDontSee('Still Hidden');

        expect(ActivityResource::getEloquentQuery()->whereKey([$this->byBoss->id, $this->aboutBoss->id])->exists())->toBeFalse();
    });

    it('leaves the same entries out of a record timeline', function (): void {
        actingAsAdmin($this->viewer);

        livewire(ActivitiesRelationManager::class, ['ownerRecord' => $this->user, 'pageClass' => EditAdmin::class])
            ->assertCanSeeTableRecords([$this->byEditor])
            ->assertCanNotSeeTableRecords([$this->byBoss]);
    });

    it('shows everything to a super admin', function (): void {
        actingAsAdmin($this->boss);

        livewire(ListActivities::class)
            ->assertCanSeeTableRecords([$this->byEditor, $this->byBoss, $this->aboutBoss]);
    });
});

describe('record timeline', function (): void {
    it('shows only the activity of that record', function (): void {
        actingAsAdmin(superAdmin());
        $subject = admin(['name' => 'Subject']);
        $other = admin(['name' => 'Other']);
        $subject->update(['name' => 'Subject Renamed']);
        $other->update(['name' => 'Other Renamed']);

        $mine = Activity::query()->where('subject_type', 'admin')->where('subject_id', $subject->id)->get();
        $theirs = Activity::query()->where('subject_type', 'admin')->where('subject_id', $other->id)->get();

        livewire(ActivitiesRelationManager::class, ['ownerRecord' => $subject, 'pageClass' => ViewAdmin::class])
            ->assertCanSeeTableRecords($mine)
            ->assertCanNotSeeTableRecords($theirs);
    });

    it('is offered only to admins who may view the activity log', function (): void {
        $subject = admin();

        actingAsAdmin(adminWith(['view_any:admin', 'view:admin', 'update:admin']));
        expect(ActivitiesRelationManager::canViewForRecord($subject, EditAdmin::class))->toBeFalse();

        actingAsAdmin(adminWith(['view_any:admin', 'view:admin', 'view_activity_log']));
        expect(ActivitiesRelationManager::canViewForRecord($subject, EditAdmin::class))->toBeTrue();
    });

    it('is registered on the admin, user and role resources', function (string $resource): void {
        expect($resource::getRelations())->toContain(ActivitiesRelationManager::class);
    })->with([
        AdminResource::class,
        UserResource::class,
        RoleResource::class,
    ]);
});

it('appears in the navigation with the permission', function (): void {
    get(panelUrl())->assertSee(ActivityResource::getNavigationLabel());
});

// A separate test on purpose: Filament builds the navigation once per
// application instance, and a test reuses one instance for all its requests.
it('is absent from the navigation without the permission', function (): void {
    actingAsAdmin(adminWith([]));

    get(panelUrl())->assertDontSee(ActivityResource::getNavigationLabel());
});

it('deletes activity older than the retention period', function (): void {
    config(['activitylog.clean_after_days' => 30]);

    $old = activity()->createdAt(now()->subDays(31))->log('old');
    $kept = activity()->createdAt(now()->subDays(29))->log('kept');

    $this->artisan('activitylog:clean', ['--force' => true])->assertSuccessful();

    expect(Activity::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(Activity::query()->whereKey($kept->id)->exists())->toBeTrue();
});

it('schedules the clean-up daily with the configured retention', function (): void {
    $event = collect(resolve(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'activitylog:clean'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 2 * * *')
        ->and($event->command)->toContain('--days='.config('activitylog.clean_after_days'))
        ->toContain('--force');
});
