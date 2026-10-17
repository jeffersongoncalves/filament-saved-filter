<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User;
use JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures\ListPosts;
use JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures\PostResource;
use JeffersonGoncalves\SavedFilter\Models\SavedFilter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('test'));
    $this->actingAs(User::query()->forceCreate(['name' => 'Ana']));
});

/** The column names in the order the table shows them; the column manager state must agree. */
function columnNames(Testable $component): array
{
    $rendered = array_keys($component->instance()->getTable()->getColumns());

    expect(collect($component->instance()->tableColumns)->pluck('name')->all())->toBe($rendered);

    return $rendered;
}

/** Drag the columns into the given order, as the column manager does. */
function reorderColumns(Testable $component, array $names): Testable
{
    $byName = collect($component->instance()->tableColumns)->keyBy('name');

    return $component->call('applyTableColumnManager', collect($names)->map(fn (string $name) => $byName[$name])->all(), true);
}

it('saves the column order of a reordered table', function () {
    $component = Livewire::test(ListPosts::class);
    reorderColumns($component, ['status', 'created_at', 'title'])
        ->callTableAction('saveSavedFilter', data: ['name' => 'Reordered']);

    expect(SavedFilter::query()->sole()->column_order)->toBe(['column:status', 'column:created_at', 'column:title']);
});

it('keeps following the table order until the user reorders it', function () {
    Livewire::test(ListPosts::class)->callTableAction('saveSavedFilter', data: ['name' => 'Default']);

    expect(SavedFilter::query()->sole()->column_order)->toBeNull();
});

it('restores the saved order and goes back to the default one on All', function () {
    $filter = SavedFilter::query()->create([
        'owner_type' => auth()->user()->getMorphClass(),
        'owner_id' => auth()->id(),
        'resource' => PostResource::class,
        'name' => 'Reordered',
        'column_order' => ['column:status', 'column:created_at', 'column:title'],
    ]);

    $component = Livewire::test(ListPosts::class)->set('activeTab', "saved-{$filter->id}");

    expect(columnNames($component))->toBe(['status', 'created_at', 'title']);

    $component->set('activeTab', 'all');

    expect(columnNames($component))->toBe(['title', 'status', 'created_at']);
});

it('skips columns the table no longer has and puts new ones last', function () {
    $filter = SavedFilter::query()->create([
        'owner_type' => auth()->user()->getMorphClass(),
        'owner_id' => auth()->id(),
        'resource' => PostResource::class,
        'name' => 'Old order',
        'column_order' => ['column:gone', 'column:created_at', 'column:status'],
    ]);

    $component = Livewire::test(ListPosts::class)->set('activeTab', "saved-{$filter->id}");

    expect(columnNames($component))->toBe(['created_at', 'status', 'title']);
});

it('orders the columns inside a group too', function () {
    $item = fn (string $type, string $name, array $columns = []) => ['type' => $type, 'name' => $name] + ($columns ? ['columns' => $columns] : []);
    $items = [
        $item('column', 'title'),
        $item('group', 'Details', [$item('column', 'author.name'), $item('column', 'status')]),
    ];
    $order = array_flip(['group:Details', 'group:Details:status', 'group:Details:author.name', 'column:title']);

    $sorted = (fn () => $this->orderSavedFilterColumns($items, $order))->call(Livewire::test(ListPosts::class)->instance());

    expect(collect($sorted)->pluck('name')->all())->toBe(['Details', 'title'])
        ->and(collect($sorted[0]['columns'])->pluck('name')->all())->toBe(['status', 'author.name']);
});

it('flags a reordered view as modified', function () {
    $filter = SavedFilter::query()->create([
        'owner_type' => auth()->user()->getMorphClass(),
        'owner_id' => auth()->id(),
        'resource' => PostResource::class,
        'name' => 'Plain',
    ]);

    $component = Livewire::test(ListPosts::class)->set('activeTab', "saved-{$filter->id}");

    expect($component->instance()->isSavedFilterModified())->toBeFalse();

    reorderColumns($component, ['created_at', 'title', 'status']);

    expect($component->instance()->isSavedFilterModified())->toBeTrue();
});
