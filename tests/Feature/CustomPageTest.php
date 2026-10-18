<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User;
use JeffersonGoncalves\Filament\SavedFilter\SavedFilterPlugin;
use JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures\ListPosts;
use JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures\Post;
use JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures\PostsReport;
use JeffersonGoncalves\SavedFilter\Models\SavedFilter;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('test'));
    $this->actingAs($this->user = User::query()->forceCreate(['name' => 'Ana', 'email' => 'ana@example.com']));

    $this->published = Post::query()->create(['title' => 'Hello world', 'status' => 'published']);
    $this->draft = Post::query()->create(['title' => 'Work in progress', 'status' => 'draft']);
});

function reportView(array $attributes = []): SavedFilter
{
    return SavedFilter::query()->create([
        'owner_type' => test()->user->getMorphClass(),
        'owner_id' => test()->user->getKey(),
        'resource' => PostsReport::class,
        'name' => 'Drafts',
        'filters' => ['status' => ['value' => 'draft']],
        ...$attributes,
    ]);
}

it('saves views of a custom table page under the page class', function () {
    Livewire::test(PostsReport::class)
        ->filterTable('status', 'draft')
        ->callTableAction('saveSavedFilter', data: ['name' => 'Drafts'])
        ->assertHasNoTableActionErrors();

    expect(SavedFilter::query()->sole()->resource)->toBe(PostsReport::class)
        ->and(Livewire::test(ListPosts::class)->instance()->getSavedFilters())->toBeEmpty();
});

it('lists and applies the views from the dropdown, tabs or not', function () {
    $view = reportView();

    Livewire::test(PostsReport::class)
        ->callTableAction("applySavedFilter{$view->id}")
        ->assertSet('activeSavedFilter', $view->id)
        ->assertCanSeeTableRecords([$this->draft])
        ->assertCanNotSeeTableRecords([$this->published]);
});

it('opens the default view and a shared ?view= on mount', function () {
    $default = reportView(['is_default' => true]);
    $shared = reportView(['name' => 'Published', 'filters' => ['status' => ['value' => 'published']]]);

    Livewire::test(PostsReport::class)
        ->assertSet('activeSavedFilter', $default->id)
        ->assertCanNotSeeTableRecords([$this->published]);

    Livewire::withQueryParams(['view' => $shared->id])->test(PostsReport::class)
        ->assertSet('activeSavedFilter', $shared->id)
        ->assertCanNotSeeTableRecords([$this->draft]);
});

it('keeps email reports to list pages', function () {
    SavedFilterPlugin::current()->emailReports();
    reportView();

    Livewire::test(PostsReport::class)
        ->assertTableActionHidden('emailSavedFilter')
        ->assertTableActionHidden('savedFilterReport');

    SavedFilterPlugin::current()->emailReports(false);
});
