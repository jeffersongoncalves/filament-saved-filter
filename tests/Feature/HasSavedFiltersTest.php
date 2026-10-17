<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use JeffersonGoncalves\Filament\SavedFilter\SavedFilterPlugin;
use JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures\ListPosts;
use JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures\Post;
use JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures\PostResource;
use JeffersonGoncalves\SavedFilter\Events\SavedFilterApplied;
use JeffersonGoncalves\SavedFilter\Models\SavedFilter;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('test'));

    $this->user = User::query()->forceCreate(['name' => 'Ana']);
    $this->actingAs($this->user);

    $this->published = Post::query()->create(['title' => 'Hello world', 'status' => 'published']);
    $this->draft = Post::query()->create(['title' => 'Work in progress', 'status' => 'draft']);
});

function savedFilter(array $attributes = [], ?User $owner = null): SavedFilter
{
    $owner ??= test()->user;

    return SavedFilter::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
        'resource' => PostResource::class,
        'name' => 'Published',
        'filters' => ['status' => ['value' => 'published']],
        'sort_column' => 'title',
        'sort_direction' => 'desc',
        'toggled_columns' => ['status' => false, 'created_at' => true],
        ...$attributes,
    ]);
}

function preset(array $attributes = []): SavedFilter
{
    return tap(savedFilter($attributes), fn (SavedFilter $filter) => $filter->update(['owner_type' => null, 'owner_id' => null, 'is_locked' => true]));
}

it('saves the current table state', function () {
    Livewire::test(ListPosts::class)
        ->filterTable('status', 'published')
        ->sortTable('title', 'desc')
        ->searchTable('Hello')
        ->callTableAction('saveSavedFilter', data: ['name' => 'Mine', 'icon' => 'heroicon-o-star', 'is_default' => true, 'is_public' => true])
        ->assertHasNoTableActionErrors()
        ->assertNotified()
        ->assertSet('activeTab', 'saved-1')
        ->assertSee('Mine');

    $filter = SavedFilter::query()->sole();

    expect($filter->only(['name', 'resource', 'sort_column', 'sort_direction', 'search', 'icon', 'is_default', 'is_public', 'is_locked']))->toBe([
        'name' => 'Mine',
        'resource' => PostResource::class,
        'sort_column' => 'title',
        'sort_direction' => 'desc',
        'search' => 'Hello',
        'icon' => 'heroicon-o-star',
        'is_default' => true,
        'is_public' => true,
        'is_locked' => false,
    ])
        ->and($filter->filters['status']['value'])->toBe('published')
        ->and($filter->toggled_columns)->toBe(['status' => true, 'created_at' => false])
        ->and($filter->isOwnedBy($this->user))->toBeTrue();
});

it('lists the saved filters as tabs and applies one', function () {
    $filter = savedFilter();

    $component = Livewire::test(ListPosts::class)
        ->assertSee('Published')
        ->set('activeTab', "saved-{$filter->id}")
        ->assertSet('activeSavedFilter', $filter->id)
        ->assertSet('tableFilters.status.value', 'published')
        ->assertSet('tableSort', 'title:desc')
        ->assertCanSeeTableRecords([$this->published])
        ->assertCanNotSeeTableRecords([$this->draft]);

    expect($component->instance()->isTableColumnToggledHidden('status'))->toBeTrue()
        ->and($component->instance()->isTableColumnToggledHidden('created_at'))->toBeFalse();

    $component->set('activeTab', 'all')
        ->assertSet('activeSavedFilter', null)
        ->assertSet('tableFilters.status.value', null)
        ->assertSet('tableSort', null)
        ->assertCanSeeTableRecords([$this->published, $this->draft]);
});

it('opens the default saved filter', function () {
    $filter = savedFilter(['is_default' => true]);

    Livewire::test(ListPosts::class)
        ->assertSet('activeTab', "saved-{$filter->id}")
        ->assertSet('activeSavedFilter', $filter->id)
        ->assertCanNotSeeTableRecords([$this->draft]);
});

it('ignores filters, columns and sorts the table no longer has', function () {
    $filter = savedFilter([
        'filters' => ['gone' => ['value' => 'x'], 'status' => ['value' => 'draft']],
        'sort_column' => 'gone',
        'toggled_columns' => ['gone' => true],
        'icon' => 'heroicon-o-does-not-exist',
    ]);

    Livewire::test(ListPosts::class)
        ->set('activeTab', "saved-{$filter->id}")
        ->assertSuccessful()
        ->assertSet('tableSort', null)
        ->assertCanSeeTableRecords([$this->draft])
        ->assertCanNotSeeTableRecords([$this->published]);
});

it('shows the public filters and presets of others, never their private ones', function () {
    $bob = User::query()->forceCreate(['name' => 'Bob']);
    $private = savedFilter(['name' => 'Bob private'], $bob);
    $public = savedFilter(['name' => 'Bob public', 'is_public' => true], $bob);
    $preset = preset(['name' => 'Company preset']);

    $component = Livewire::test(ListPosts::class)
        ->assertSee(['Bob public', 'Company preset'])
        ->assertDontSee('Bob private')
        ->set('activeTab', "saved-{$private->id}")
        ->assertSet('activeSavedFilter', null);

    expect($component->instance()->getSavedFilters()->modelKeys())->toEqualCanonicalizing([$public->id, $preset->id]);
});

it('applies saved filters from the dropdown', function () {
    SavedFilterPlugin::current()->dropdown();
    $filter = savedFilter();

    Livewire::test(ListPosts::class)
        ->callTableAction("applySavedFilter{$filter->id}")
        ->assertSet('activeSavedFilter', $filter->id)
        ->assertSet('activeTab', null)
        ->assertCanNotSeeTableRecords([$this->draft]);

    SavedFilterPlugin::current()->tabs();
});

it('dispatches the applied event', function () {
    Event::fake([SavedFilterApplied::class]);
    $filter = savedFilter();

    Livewire::test(ListPosts::class)->set('activeTab', "saved-{$filter->id}");

    Event::assertDispatched(SavedFilterApplied::class, fn (SavedFilterApplied $event) => $event->savedFilter->is($filter) && $event->user?->is($this->user));
});

it('updates the active filter, but not a locked preset', function () {
    $filter = savedFilter();
    $preset = preset(['name' => 'Company preset']);

    Livewire::test(ListPosts::class)
        ->set('activeTab', "saved-{$filter->id}")
        ->searchTable('world')
        ->callTableAction('updateSavedFilter')
        ->assertNotified()
        ->set('activeTab', "saved-{$preset->id}")
        ->assertTableActionHidden('updateSavedFilter');

    expect($filter->refresh()->search)->toBe('world');
});

it('renames, reorders and deletes from the manage slide-over', function () {
    $first = savedFilter(['name' => 'First']);
    $second = savedFilter(['name' => 'Second']);
    $third = savedFilter(['name' => 'Third']);
    $preset = preset(['name' => 'Company preset']);

    Livewire::test(ListPosts::class)
        ->callTableAction('manageSavedFilters', data: ['saved_filters' => [
            ['id' => $third->id, 'name' => 'Third renamed', 'is_default' => true],
            ['id' => $first->id, 'name' => 'First'],
            ['id' => $preset->id, 'name' => 'Hijacked'],
        ]])
        ->assertHasNoTableActionErrors();

    expect(SavedFilter::query()->ownedBy($this->user)->ordered()->pluck('name')->all())->toBe(['Third renamed', 'First'])
        ->and($third->refresh()->is_default)->toBeTrue()
        ->and(SavedFilter::query()->find($second->id))->toBeNull()
        ->and($preset->refresh()->name)->toBe('Company preset');
});

it('saves and restores records per page and grouping', function () {
    Livewire::test(ListPosts::class)
        ->set('tableRecordsPerPage', 25)
        ->set('tableGrouping', 'status:desc')
        ->callTableAction('saveSavedFilter', data: ['name' => 'Grouped']);

    $filter = SavedFilter::query()->sole();

    expect($filter->only(['records_per_page', 'grouping', 'grouping_direction']))
        ->toBe(['records_per_page' => '25', 'grouping' => 'status', 'grouping_direction' => 'desc']);

    Livewire::test(ListPosts::class)
        ->set('activeTab', "saved-{$filter->id}")
        ->assertSet('tableRecordsPerPage', 25)
        ->assertSet('tableGrouping', 'status:desc')
        ->set('activeTab', 'all')
        ->assertSet('tableRecordsPerPage', 10)
        ->assertSet('tableGrouping', null);
});

it('ignores pagination options and groups the table no longer has', function () {
    $filter = savedFilter(['records_per_page' => '999', 'grouping' => 'gone']);

    Livewire::test(ListPosts::class)
        ->set('activeTab', "saved-{$filter->id}")
        ->assertSet('tableRecordsPerPage', 10)
        ->assertSet('tableGrouping', null);
});

it('badges presets, shared views and views of colleagues', function () {
    $bob = User::query()->forceCreate(['name' => 'Bob']);
    savedFilter(['name' => 'Bob public', 'is_public' => true], $bob);
    savedFilter(['name' => 'Mine shared', 'is_public' => true]);
    preset(['name' => 'Company preset']);

    $tabs = Livewire::test(ListPosts::class)->instance()->getSavedFilterTabs();

    expect(collect($tabs)->map->getBadge()->filter()->values()->all())->toBe(['Bob', 'Shared', 'Preset']);
});

it('binds keyboard shortcuts to save and manage', function () {
    savedFilter();

    Livewire::test(ListPosts::class)
        ->assertTableActionExists('saveSavedFilter', fn ($action) => $action->getKeyBindings() === ['mod+shift+s'])
        ->assertTableActionExists('manageSavedFilters', fn ($action) => $action->getKeyBindings() === ['mod+shift+m']);
});

it('switches views by position and cycles through them', function () {
    $first = savedFilter(['name' => 'First']);
    $second = savedFilter(['name' => 'Second', 'filters' => ['status' => ['value' => 'draft']]]);

    Livewire::test(ListPosts::class)
        ->call('selectSavedFilter', 2)
        ->assertSet('activeTab', "saved-{$second->id}")
        ->assertCanNotSeeTableRecords([$this->published])
        ->call('cycleSavedFilter', 1)
        ->assertSet('activeTab', 'all')
        ->assertSet('activeSavedFilter', null)
        ->call('cycleSavedFilter', 1)
        ->assertSet('activeSavedFilter', $first->id)
        ->call('cycleSavedFilter', -1)
        ->assertSet('activeSavedFilter', null)
        ->call('selectSavedFilter', 9)
        ->assertSet('activeSavedFilter', null);
});

it('renders the Alt+N listener only when turned on', function () {
    savedFilter();

    Livewire::test(ListPosts::class)->assertDontSeeHtml('data-saved-filter-shortcuts');

    SavedFilterPlugin::current()->tabShortcuts();

    Livewire::test(ListPosts::class)->assertSeeHtml('data-saved-filter-shortcuts');

    SavedFilterPlugin::current()->tabShortcuts(false);
});

it('explains the manage slide-over and labels its submit button', function () {
    savedFilter();

    Livewire::test(ListPosts::class)
        ->assertTableActionExists('manageSavedFilters', fn ($action) => $action->getModalDescription() === 'Rename, reorder, set the default or delete the views you no longer use.'
            && $action->getModalSubmitActionLabel() === 'Save changes');
});

it('lets preset managers save locked presets', function () {
    SavedFilterPlugin::current()->canManagePresets();

    Livewire::test(ListPosts::class)
        ->callTableAction('saveSavedFilter', data: ['name' => 'For everyone', 'as_preset' => true]);

    expect(SavedFilter::query()->sole())
        ->isPreset()->toBeTrue()
        ->is_locked->toBeTrue()
        ->is_public->toBeTrue();

    SavedFilterPlugin::current()->canManagePresets(false);
});

it('exports the visible filters', function () {
    savedFilter();

    Livewire::test(ListPosts::class)
        ->callTableAction('exportSavedFilters')
        ->assertFileDownloaded('saved-filters.json');
});

it('imports filters of this table as the user\'s own', function () {
    $json = json_encode(['saved_filters' => [
        ['resource' => PostResource::class, 'name' => 'Imported', 'filters' => ['status' => ['value' => 'draft']], 'is_locked' => true, 'is_public' => true],
        ['resource' => 'Other\\Resource', 'name' => 'Elsewhere'],
    ]]);

    Livewire::test(ListPosts::class)
        ->callTableAction('importSavedFilters', data: ['file' => UploadedFile::fake()->createWithContent('saved-filters.json', $json)])
        ->assertNotified();

    expect(SavedFilter::query()->sole())
        ->name->toBe('Imported')
        ->is_locked->toBeFalse()
        ->is_public->toBeTrue()
        ->isOwnedBy($this->user)->toBeTrue();
});

it('uses translated labels', function () {
    app()->setLocale('pt_BR');

    expect(__('filament-saved-filter::saved-filter.actions.group'))->toBe('Visualizações');
});

it('has every key in every locale', function () {
    $keys = fn (string $locale) => array_keys(Arr::dot(require __DIR__."/../../resources/lang/{$locale}/saved-filter.php"));
    $locales = array_map('basename', glob(__DIR__.'/../../resources/lang/*', GLOB_ONLYDIR));

    expect($locales)->toHaveCount(19);

    foreach ($locales as $locale) {
        expect($keys($locale))->toEqualCanonicalizing($keys('en'));
    }
});
