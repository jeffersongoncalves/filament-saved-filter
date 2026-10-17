<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Mail;
use JeffersonGoncalves\Filament\SavedFilter\SavedFilterPlugin;
use JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures\ListPosts;
use JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures\Member;
use JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures\Post;
use JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures\PostResource;
use JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures\Team;
use JeffersonGoncalves\SavedFilter\Contracts\BuildsSavedFilterReports;
use JeffersonGoncalves\SavedFilter\Mail\SavedFilterReportMail;
use JeffersonGoncalves\SavedFilter\Models\SavedFilter;
use JeffersonGoncalves\SavedFilter\Models\SavedFilterReport;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('test'));
    SavedFilterPlugin::current()->emailReports();
    Mail::fake();

    $this->ana = User::query()->forceCreate(['name' => 'Ana', 'email' => 'ana@example.com']);
    $this->bob = User::query()->forceCreate(['name' => 'Bob', 'email' => 'bob@example.com']);
    $this->actingAs($this->ana);

    Post::query()->create(['title' => 'Hello world', 'status' => 'published']);
    Post::query()->create(['title' => 'Work in progress', 'status' => 'draft']);

    $this->drafts = SavedFilter::query()->create([
        'owner_type' => $this->ana->getMorphClass(),
        'owner_id' => $this->ana->getKey(),
        'resource' => PostResource::class,
        'name' => 'Drafts',
        'filters' => ['status' => ['value' => 'draft']],
        'is_public' => true,
    ]);
});

afterEach(fn () => SavedFilterPlugin::current()->emailReports(false));

function subscribe(User $user, SavedFilter $filter, array $data = []): void
{
    test()->actingAs($user);

    Livewire::test(ListPosts::class)
        ->set('activeTab', "saved-{$filter->id}")
        ->callTableAction('savedFilterReport', data: [
            'is_active' => true, 'frequency' => 'daily', 'time' => '08:00', 'format' => 'csv', ...$data,
        ])
        ->assertHasNoTableActionErrors();
}

it('hides the email actions unless turned on', function () {
    SavedFilterPlugin::current()->emailReports(false);

    Livewire::test(ListPosts::class)
        ->set('activeTab', "saved-{$this->drafts->id}")
        ->assertTableActionHidden('emailSavedFilter')
        ->assertTableActionHidden('savedFilterReport');
});

it('emails the current view on demand, built with the page exporter', function () {
    Livewire::test(ListPosts::class)
        ->set('activeTab', "saved-{$this->drafts->id}")
        ->callTableAction('emailSavedFilter', data: ['format' => 'csv'])
        ->assertNotified();

    Mail::assertSent(SavedFilterReportMail::class, function (SavedFilterReportMail $mail) {
        return $mail->hasTo('ana@example.com')
            && $mail->viewName === 'Drafts'
            && $mail->count === 1
            && $mail->content === "Title,Status\n\"Work in progress\",draft\n";
    });
});

it('builds an xlsx file', function () {
    Livewire::test(ListPosts::class)->callTableAction('emailSavedFilter', data: ['format' => 'xlsx']);

    Mail::assertSent(SavedFilterReportMail::class, fn (SavedFilterReportMail $mail) => $mail->count === 2
        && str_starts_with($mail->content, 'PK')
        && str_ends_with($mail->fileName, '.xlsx'));
});

it('lets each user subscribe to a shared view, and sends it to that user only', function () {
    subscribe($this->bob, $this->drafts, ['frequency' => 'weekly', 'day' => 3, 'time' => '09:30', 'format' => 'xlsx']);

    expect(SavedFilterReport::query()->sole())
        ->user_id->toBe((string) $this->bob->getKey())
        ->frequency->toBe('weekly')
        ->day->toBe(3)
        ->time->toBe('09:30')
        ->format->toBe('xlsx')
        ->is_active->toBeTrue()
        ->context->toEqual(['panel' => 'test', 'page' => ListPosts::class, 'tenant' => null, 'saved_filter_id' => $this->drafts->id, 'skip_empty' => false]); // MySQL JSON doesn't keep key order
});

it('sends due reports once per occurrence and skips turned off ones', function () {
    $this->travelTo(now()->setTime(7, 0));
    subscribe($this->bob, $this->drafts);
    subscribe($this->ana, $this->drafts, ['is_active' => false]);

    $this->artisan('saved-filter:send-reports')->assertSuccessful();
    Mail::assertNothingSent();

    $this->travelTo(now()->setTime(8, 10));
    $this->artisan('saved-filter:send-reports');
    $this->artisan('saved-filter:send-reports');

    Mail::assertSent(SavedFilterReportMail::class, 1);
    Mail::assertSent(SavedFilterReportMail::class, fn (SavedFilterReportMail $mail) => $mail->hasTo('bob@example.com') && $mail->count === 1);

    $this->travelTo(now()->addDay());
    $this->artisan('saved-filter:send-reports');

    Mail::assertSent(SavedFilterReportMail::class, 2);
});

it('stops sending a view that is no longer shared', function () {
    $this->travelTo(now()->setTime(7, 0));
    subscribe($this->bob, $this->drafts);
    $this->drafts->update(['is_public' => false]);

    $this->travelTo(now()->setTime(8, 10));
    $this->artisan('saved-filter:send-reports');

    Mail::assertNothingSent();
});

it('keeps the settings of a turned off report and fills them back', function () {
    subscribe($this->bob, $this->drafts, ['is_active' => false, 'frequency' => 'monthly', 'day' => 15]);

    Livewire::test(ListPosts::class)
        ->set('activeTab', "saved-{$this->drafts->id}")
        ->mountTableAction('savedFilterReport')
        ->assertTableActionDataSet(['is_active' => false, 'frequency' => 'monthly', 'day' => 15]);
});

it('schedules from the All tab by picking the view, loading its settings', function () {
    $published = SavedFilter::query()->create([
        'owner_type' => $this->ana->getMorphClass(),
        'owner_id' => $this->ana->getKey(),
        'resource' => PostResource::class,
        'name' => 'Published',
        'filters' => ['status' => ['value' => 'published']],
        'order_column' => 5,
    ]);
    subscribe($this->ana, $published, ['frequency' => 'monthly', 'day' => 9]);

    Livewire::test(ListPosts::class)
        ->assertSet('activeSavedFilter', null)
        ->assertTableActionVisible('savedFilterReport')
        ->mountTableAction('savedFilterReport')
        ->assertTableActionDataSet(['saved_filter_id' => $this->drafts->id, 'frequency' => 'weekly'])
        ->setTableActionData(['saved_filter_id' => $published->id])
        ->assertTableActionDataSet(['frequency' => 'monthly', 'day' => 9])
        ->setTableActionData(['saved_filter_id' => $this->drafts->id, 'frequency' => 'daily'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($this->drafts->reports()->sole()->frequency)->toBe('daily')
        ->and($published->reports()->sole()->frequency)->toBe('monthly');
});

it('refuses to schedule a view the user does not see', function () {
    $private = SavedFilter::query()->create([
        'owner_type' => $this->bob->getMorphClass(),
        'owner_id' => $this->bob->getKey(),
        'resource' => PostResource::class,
        'name' => 'Bob private',
    ]);

    Livewire::test(ListPosts::class)
        ->callTableAction('savedFilterReport', data: ['saved_filter_id' => $private->id, 'frequency' => 'daily', 'time' => '08:00', 'format' => 'csv']);

    expect(SavedFilterReport::query()->count())->toBe(0);
});

it('links the email to the view, or to the page once the table drifted from it', function () {
    $component = Livewire::test(ListPosts::class)
        ->set('activeTab', "saved-{$this->drafts->id}")
        ->callTableAction('emailSavedFilter', data: ['format' => 'csv']);

    Mail::assertSent(SavedFilterReportMail::class, fn (SavedFilterReportMail $mail) => $mail->url === ListPosts::getUrl(['tab' => "saved-{$this->drafts->id}"], panel: 'test')
        && $mail->schedule === null);

    Mail::fake();
    $component->searchTable('Work')->callTableAction('emailSavedFilter', data: ['format' => 'csv']);

    Mail::assertSent(SavedFilterReportMail::class, fn (SavedFilterReportMail $mail) => $mail->url === ListPosts::getUrl(panel: 'test'));
});

it('tells when a scheduled report goes out and links to its view', function () {
    $this->travelTo(now()->setTime(7, 0));
    subscribe($this->bob, $this->drafts);

    $this->travelTo(now()->setTime(8, 10));
    $this->artisan('saved-filter:send-reports');

    Mail::assertSent(SavedFilterReportMail::class, fn (SavedFilterReportMail $mail) => filled($mail->schedule)
        && $mail->url === ListPosts::getUrl(['tab' => "saved-{$this->drafts->id}"], panel: 'test'));
});

it('skips an empty report only when asked to', function () {
    $this->travelTo(now()->setTime(7, 0));
    $this->drafts->update(['filters' => ['status' => ['value' => 'archived']]]);
    subscribe($this->bob, $this->drafts, ['skip_empty' => true]);
    subscribe($this->ana, $this->drafts);

    $this->travelTo(now()->setTime(8, 10));
    $this->artisan('saved-filter:send-reports');

    Mail::assertSent(SavedFilterReportMail::class, 1);
    Mail::assertSent(SavedFilterReportMail::class, fn (SavedFilterReportMail $mail) => $mail->hasTo('ana@example.com') && $mail->count === 0);
});

it('neutralizes spreadsheet formulas in the records', function () {
    Post::query()->create(['title' => '=HYPERLINK("http://evil.test")', 'status' => '-5']);

    Livewire::test(ListPosts::class)->callTableAction('emailSavedFilter', data: ['format' => 'csv']);

    Mail::assertSent(SavedFilterReportMail::class, fn (SavedFilterReportMail $mail) => str_contains($mail->content, "'=HYPERLINK")
        && str_contains($mail->content, ',-5'));
});

it('builds a report only inside the subscriber\'s own tenant', function () {
    $mine = Team::query()->create(['name' => 'Mine']);
    $theirs = Team::query()->create(['name' => 'Theirs']);
    Post::query()->create(['title' => 'Our post', 'status' => 'draft', 'team_id' => $mine->id]);
    Post::query()->create(['title' => 'Their post', 'status' => 'draft', 'team_id' => $theirs->id]);
    $member = Member::query()->create(['name' => 'Cid', 'email' => 'cid@example.com', 'team_id' => $mine->id]);

    $build = fn (array $context) => app(BuildsSavedFilterReports::class)
        ->build($member, [], 'Posts', 'csv', ['panel' => 'tenant', 'page' => ListPosts::class, ...$context]);

    expect($build(['tenant_id' => $mine->id]))
        ->count->toBe(1)
        ->content->toContain('Our post')->not->toContain('Their post')
        ->and($build(['tenant_id' => $theirs->id]))->toBeNull()
        ->and($build(['tenant' => null]))->toBeNull();
});

it('drops the previous report\'s tenant, as a queue worker would keep it', function () {
    $team = Team::query()->create(['name' => 'Mine']);
    Post::query()->create(['title' => 'Team post', 'status' => 'draft', 'team_id' => $team->id]);
    $member = Member::query()->create(['name' => 'Cid', 'email' => 'cid@example.com', 'team_id' => $team->id]);
    $builder = app(BuildsSavedFilterReports::class);

    $builder->build($member, [], 'Posts', 'csv', ['panel' => 'tenant', 'page' => ListPosts::class, 'tenant_id' => $team->id]);

    expect($builder->build($this->ana, [], 'Posts', 'csv', ['panel' => 'test', 'page' => ListPosts::class])->count)->toBe(3)
        ->and(Filament::getTenant())->toBeNull();
});
