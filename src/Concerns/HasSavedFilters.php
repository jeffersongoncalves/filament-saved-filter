<?php

namespace JeffersonGoncalves\Filament\SavedFilter\Concerns;

use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use JeffersonGoncalves\Filament\SavedFilter\SavedFilterPlugin;
use JeffersonGoncalves\SavedFilter\Events\SavedFilterApplied;
use JeffersonGoncalves\SavedFilter\Models\SavedFilter;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Save, share and restore the table state (filters, sort, search, toggled columns) of a ListRecords page.
 */
trait HasSavedFilters
{
    public ?int $activeSavedFilter = null;

    /** Hash of the table state right after the active filter was applied or saved: tells when it was changed. */
    public ?string $savedFilterSnapshot = null;

    /** Applied once the table exists: during mount() it doesn't yet. */
    protected ?int $pendingSavedFilter = null;

    /** @var Collection<int, SavedFilter>|null */
    protected ?Collection $cachedSavedFilters = null;

    public function table(Table $table): Table
    {
        return $this->configureSavedFilters(parent::table($table));
    }

    /** Call it from your own table() if the page overrides it. */
    public function configureSavedFilters(Table $table): Table
    {
        return $table->pushHeaderActions([$this->getSavedFilterActionGroup()]);
    }

    /**
     * @return array<string | int, Tab>
     */
    public function getTabs(): array
    {
        return $this->getSavedFilterTabs();
    }

    /**
     * An "All" tab plus one tab per saved filter; spread it into your own getTabs() to combine both.
     *
     * @return array<string | int, Tab>
     */
    public function getSavedFilterTabs(): array
    {
        if (SavedFilterPlugin::current()->getDisplayMode() !== SavedFilterPlugin::TABS || $this->getSavedFilters()->isEmpty()) {
            return [];
        }

        $tabs = ['all' => Tab::make(__('filament-saved-filter::saved-filter.tabs.all'))];

        foreach ($this->getSavedFilters() as $filter) {
            $tabs["saved-{$filter->getKey()}"] = Tab::make($filter->name)
                ->icon($this->getSavedFilterIcon($filter))
                ->badge($this->getSavedFilterBadge($filter))
                ->badgeColor('gray');
        }

        return $tabs;
    }

    protected function loadDefaultActiveTab(): void
    {
        $hasUrlState = filled($this->activeTab) || filled($this->tableFilters) || filled($this->tableSearch) || filled($this->tableSortColumn);

        if (! $hasUrlState && ($default = $this->getDefaultSavedFilter())) {
            $this->pendingSavedFilter = $default->getKey();
            $this->activeTab = SavedFilterPlugin::current()->getDisplayMode() === SavedFilterPlugin::TABS ? "saved-{$default->getKey()}" : null;
        }

        parent::loadDefaultActiveTab();
    }

    public function bootedHasSavedFilters(): void
    {
        if ($this->pendingSavedFilter && ($filter = $this->findSavedFilter($this->pendingSavedFilter))) {
            $this->applySavedFilter($filter);
        }

        $this->pendingSavedFilter = null;
    }

    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();

        $id = str_starts_with((string) $this->activeTab, 'saved-') ? (int) substr((string) $this->activeTab, 6) : null;

        if ($id && ($filter = $this->findSavedFilter($id))) {
            $this->applySavedFilter($filter);
        } elseif ($this->activeSavedFilter !== null) {
            $this->resetSavedFilterState();
        }
    }

    /** The table key the filters are stored under. */
    public function getSavedFilterResource(): string
    {
        return static::getResource();
    }

    protected function getSavedFilterOwner(): ?Model
    {
        $user = Filament::auth()->user();

        return $user instanceof Model ? $user : null;
    }

    protected function getSavedFilterTenant(): ?Model
    {
        return SavedFilterPlugin::current()->isScopedToTenant() ? Filament::getTenant() : null;
    }

    /**
     * @return class-string<SavedFilter>
     */
    protected function getSavedFilterModel(): string
    {
        return config('saved-filter.model', SavedFilter::class);
    }

    /**
     * @return Builder<SavedFilter>
     */
    protected function getSavedFilterQuery(): Builder
    {
        return $this->getSavedFilterModel()::query()
            ->forResource($this->getSavedFilterResource())
            ->forTenant($this->getSavedFilterTenant());
    }

    /**
     * The owner's filters, the public ones and the presets.
     *
     * @return Collection<int, SavedFilter>
     */
    public function getSavedFilters(): Collection
    {
        return $this->cachedSavedFilters ??= $this->getSavedFilterQuery()
            ->visibleTo($this->getSavedFilterOwner())
            ->with('owner')
            ->ordered()
            ->get();
    }

    /** Who else sees it: "Preset", the author of a colleague's view, or "Shared" for your own public one. */
    protected function getSavedFilterBadge(SavedFilter $filter): ?string
    {
        if ($filter->isPreset()) {
            return __('filament-saved-filter::saved-filter.badges.preset');
        }

        if (! $filter->isOwnedBy($this->getSavedFilterOwner())) {
            return ($filter->owner ? Filament::getUserName($filter->owner) : null) ?: __('filament-saved-filter::saved-filter.badges.shared');
        }

        return $filter->is_public ? __('filament-saved-filter::saved-filter.badges.shared') : null;
    }

    /** Jump to the view at a position: 0 = "All" (no saved filter), 1 = the first saved filter… */
    public function selectSavedFilter(int $position): void
    {
        if ($position === 0) {
            $this->switchSavedFilter(null);
        } elseif ($filter = $this->getSavedFilters()->values()->get($position - 1)) {
            $this->switchSavedFilter($filter);
        }
    }

    /** Move to the next (1) or previous (-1) view, wrapping around through "All". */
    public function cycleSavedFilter(int $step): void
    {
        $ids = [null, ...$this->getSavedFilters()->modelKeys()];
        $current = array_search($this->activeSavedFilter, $ids, true);
        $next = $ids[((int) $current + $step + count($ids)) % count($ids)];

        $this->switchSavedFilter($next === null ? null : $this->findSavedFilter($next));
    }

    protected function switchSavedFilter(?SavedFilter $filter): void
    {
        if (SavedFilterPlugin::current()->getDisplayMode() === SavedFilterPlugin::TABS) {
            $this->activeTab = $filter ? "saved-{$filter->getKey()}" : 'all';
            $this->updatedActiveTab();

            return;
        }

        $filter ? $this->applySavedFilter($filter) : $this->resetSavedFilterState();
    }

    protected function findSavedFilter(int $id): ?SavedFilter
    {
        return $this->getSavedFilters()->firstWhere('id', $id);
    }

    /** The owner's own default, otherwise a preset marked as default. */
    protected function getDefaultSavedFilter(): ?SavedFilter
    {
        $owner = $this->getSavedFilterOwner();
        $defaults = $this->getSavedFilters()->where('is_default', true);

        return $defaults->first(fn (SavedFilter $filter) => $filter->isOwnedBy($owner))
            ?? $defaults->first(fn (SavedFilter $filter) => $filter->isPreset());
    }

    protected function canEditSavedFilter(SavedFilter $filter): bool
    {
        if (SavedFilterPlugin::current()->userCanManagePresets() && ($filter->isPreset() || $filter->isOwnedBy($this->getSavedFilterOwner()))) {
            return true;
        }

        return ! $filter->is_locked && $filter->isOwnedBy($this->getSavedFilterOwner());
    }

    /**
     * @return Collection<int, SavedFilter>
     */
    protected function getEditableSavedFilters(): Collection
    {
        return $this->getSavedFilters()->filter(fn (SavedFilter $filter) => $this->canEditSavedFilter($filter))->values();
    }

    protected function flushSavedFilters(): void
    {
        $this->cachedSavedFilters = null;

        // Same as getCachedTabs(), so a tab added or removed shows up in this render.
        $this->cachedTabs = $this->getTabs();
    }

    public function applySavedFilter(SavedFilter $filter): void
    {
        $this->applySavedFilterState($filter->only([
            'filters', 'sort_column', 'sort_direction', 'search', 'toggled_columns', 'records_per_page', 'grouping', 'grouping_direction',
        ]));

        $this->activeSavedFilter = $filter->getKey();
        $this->takeSavedFilterSnapshot();

        SavedFilterApplied::dispatch($filter, $this->getSavedFilterOwner());
    }

    public function resetSavedFilterState(): void
    {
        $this->applySavedFilterState([]);

        $this->activeSavedFilter = null;
        $this->savedFilterSnapshot = null;
    }

    /** Whether the table was changed since the active filter was applied. */
    public function isSavedFilterModified(): bool
    {
        return $this->activeSavedFilter !== null && $this->savedFilterSnapshot !== $this->hashSavedFilterState();
    }

    protected function takeSavedFilterSnapshot(): void
    {
        $this->savedFilterSnapshot = $this->hashSavedFilterState();
    }

    protected function hashSavedFilterState(): string
    {
        return md5((string) json_encode($this->getCurrentSavedFilterState()));
    }

    /**
     * Restore a state on the table (empty = the table's defaults). Filters, sort columns, toggled columns,
     * pagination options and groups the table no longer has are ignored.
     *
     * @param  array<string, mixed>  $state  SavedFilter attributes
     */
    protected function applySavedFilterState(array $state): void
    {
        $table = $this->getTable();

        $this->getTableFiltersForm()->fill(array_intersect_key($state['filters'] ?? [], $table->getFilters()));
        $table->hasDeferredFilters() ? $this->applyTableFilters() : $this->handleTableFilterUpdates();

        $sortColumn = $state['sort_column'] ?? null;
        $this->tableSortColumn = filled($sortColumn) && $table->getSortableVisibleColumn($sortColumn) ? $sortColumn : null;
        $this->tableSortDirection = $this->tableSortColumn ? (($state['sort_direction'] ?? null) === 'desc' ? 'desc' : 'asc') : null;
        $this->updatedTableSortColumn();

        $this->tableSearch = (string) ($state['search'] ?? '');
        $this->updatedTableSearch();

        if ($table->isPaginated()) {
            $perPage = (string) ($state['records_per_page'] ?? '');
            $option = collect($table->getPaginationPageOptions())->first(fn ($option) => (string) $option === $perPage);
            $this->tableRecordsPerPage = $option ?? $table->getDefaultPaginationPageOption();
            $this->updatedTableRecordsPerPage();
        }

        $grouping = $state['grouping'] ?? null;
        $this->tableGrouping = filled($grouping) && $table->getGroup($grouping) ? $grouping : null;
        $this->tableGroupingDirection = $this->tableGrouping ? (($state['grouping_direction'] ?? null) === 'desc' ? 'desc' : 'asc') : null;
        $this->updatedTableGroupColumn();

        // Toggle state is nested by column name ("author.name" => ['author' => ['name' => true]]); saved flat.
        $toggledColumns = $state['toggled_columns'] ?? [];
        $toggleState = $this->getDefaultTableColumnToggleState();

        foreach (Arr::only($toggledColumns, array_keys(Arr::dot($toggleState))) as $name => $visible) {
            data_set($toggleState, $name, (bool) $visible);
        }

        $this->toggledTableColumns = $toggleState;
        $this->updatedToggledTableColumns();
    }

    /**
     * @return array{filters: array<string, mixed>, sort_column: ?string, sort_direction: ?string, search: ?string, toggled_columns: array<string, bool>, records_per_page: ?string, grouping: ?string, grouping_direction: ?string}
     */
    protected function getCurrentSavedFilterState(): array
    {
        $toggled = Arr::dot($this->getDefaultTableColumnToggleState());

        foreach ($toggled as $name => $visible) {
            $toggled[$name] = ! $this->isTableColumnToggledHidden($name);
        }

        return [
            'filters' => $this->tableFilters ?? [],
            'sort_column' => $this->getTableSortColumn(),
            'sort_direction' => $this->getTableSortColumn() ? $this->getTableSortDirection() : null,
            'search' => filled($this->tableSearch) ? (string) $this->tableSearch : null,
            'toggled_columns' => $toggled,
            'records_per_page' => $this->getTable()->isPaginated() && filled($this->tableRecordsPerPage) ? (string) $this->tableRecordsPerPage : null,
            'grouping' => filled($this->tableGrouping) ? $this->tableGrouping : null,
            'grouping_direction' => filled($this->tableGrouping) ? ($this->getTableGroupingDirection() ?? 'asc') : null,
        ];
    }

    public function getSavedFilterActionGroup(): ActionGroup
    {
        $plugin = SavedFilterPlugin::current();

        $apply = $plugin->getDisplayMode() === SavedFilterPlugin::DROPDOWN
            ? $this->getSavedFilters()->map(fn (SavedFilter $filter) => Action::make("applySavedFilter{$filter->getKey()}")
                ->label($filter->name)
                ->icon($this->activeSavedFilter === $filter->getKey() ? 'heroicon-m-check' : $this->getSavedFilterIcon($filter))
                ->color($filter->color ?: 'gray')
                ->badge($this->getSavedFilterBadge($filter))
                ->badgeColor('gray')
                ->action(fn () => $this->applySavedFilter($filter)))
                ->all()
            : [];

        return ActionGroup::make([
            ActionGroup::make($apply)->dropdown(false),
            ActionGroup::make([
                $this->getSaveSavedFilterAction(),
                $this->getUpdateSavedFilterAction(),
                $this->getRevertSavedFilterAction(),
                $this->getResetSavedFilterAction(),
                $this->getManageSavedFiltersAction(),
                $this->getExportSavedFiltersAction()->visible($plugin->isExportable()),
                $this->getImportSavedFiltersAction()->visible($plugin->isImportable()),
            ])->dropdown(false),
        ])
            ->label(__('filament-saved-filter::saved-filter.actions.group'))
            ->icon('heroicon-o-bookmark')
            ->color('gray')
            ->badge(fn () => $this->isSavedFilterModified() ? __('filament-saved-filter::saved-filter.badges.modified') : null)
            ->badgeColor('warning')
            ->button();
    }

    protected function getSaveSavedFilterAction(): Action
    {
        $plugin = SavedFilterPlugin::current();

        return Action::make('saveSavedFilter')
            ->label(__('filament-saved-filter::saved-filter.actions.save.label'))
            ->icon('heroicon-o-plus')
            ->keyBindings($plugin->getSaveKeyBindings())
            ->modalWidth('lg')
            ->form([
                ...$this->getSavedFilterFormFields(),
                Toggle::make('as_preset')
                    ->label(__('filament-saved-filter::saved-filter.fields.as_preset'))
                    ->visible($plugin->userCanManagePresets()),
            ])
            ->action(function (array $data) use ($plugin): void {
                $preset = $plugin->userCanManagePresets() && ($data['as_preset'] ?? false);
                $owner = $preset ? null : $this->getSavedFilterOwner();

                $filter = $this->getSavedFilterModel()::query()->create([
                    ...$this->getCurrentSavedFilterState(),
                    'owner_type' => $owner?->getMorphClass(),
                    'owner_id' => $owner?->getKey(),
                    'tenant_id' => $this->getSavedFilterTenant()?->getKey(),
                    'resource' => $this->getSavedFilterResource(),
                    'name' => $data['name'],
                    'icon' => $data['icon'] ?? null,
                    'color' => $data['color'] ?? null,
                    'is_default' => (bool) ($data['is_default'] ?? false),
                    'is_public' => $preset || ($plugin->userCanShare() && ($data['is_public'] ?? false)),
                    'is_locked' => $preset,
                    'order_column' => (int) $this->getSavedFilters()->max('order_column') + 1,
                ]);

                $this->flushSavedFilters();
                $this->activeSavedFilter = $filter->getKey();
                $this->takeSavedFilterSnapshot();

                if ($plugin->getDisplayMode() === SavedFilterPlugin::TABS) {
                    $this->activeTab = "saved-{$filter->getKey()}";
                }

                Notification::make()->success()->title(__('filament-saved-filter::saved-filter.actions.save.notification'))->send();
            });
    }

    protected function getUpdateSavedFilterAction(): Action
    {
        $active = fn (): ?SavedFilter => $this->activeSavedFilter ? $this->findSavedFilter($this->activeSavedFilter) : null;

        return Action::make('updateSavedFilter')
            ->label(fn () => __('filament-saved-filter::saved-filter.actions.update.label', ['name' => $active()?->name]))
            ->icon('heroicon-o-arrow-path')
            ->requiresConfirmation()
            ->visible(fn () => ($filter = $active()) && $this->canEditSavedFilter($filter))
            ->action(function () use ($active): void {
                $filter = $active();

                if (! $filter || ! $this->canEditSavedFilter($filter)) {
                    return;
                }

                $filter->update($this->getCurrentSavedFilterState());
                $this->flushSavedFilters();
                $this->takeSavedFilterSnapshot();

                Notification::make()->success()->title(__('filament-saved-filter::saved-filter.actions.update.notification'))->send();
            });
    }

    protected function getRevertSavedFilterAction(): Action
    {
        $active = fn (): ?SavedFilter => $this->activeSavedFilter ? $this->findSavedFilter($this->activeSavedFilter) : null;

        return Action::make('revertSavedFilter')
            ->label(fn () => __('filament-saved-filter::saved-filter.actions.revert.label', ['name' => $active()?->name]))
            ->icon('heroicon-o-arrow-uturn-left')
            ->visible(fn () => $this->isSavedFilterModified())
            ->action(fn () => ($filter = $active()) ? $this->applySavedFilter($filter) : null);
    }

    protected function getResetSavedFilterAction(): Action
    {
        return Action::make('resetSavedFilter')
            ->label(__('filament-saved-filter::saved-filter.actions.reset.label'))
            ->icon('heroicon-o-x-mark')
            ->visible(fn () => $this->activeSavedFilter !== null)
            ->action(function (): void {
                $this->resetSavedFilterState();

                if (str_starts_with((string) $this->activeTab, 'saved-')) {
                    $this->activeTab = 'all';
                }
            });
    }

    protected function getManageSavedFiltersAction(): Action
    {
        $plugin = SavedFilterPlugin::current();

        return Action::make('manageSavedFilters')
            ->label(__('filament-saved-filter::saved-filter.actions.manage.label'))
            ->modalHeading(__('filament-saved-filter::saved-filter.actions.manage.label'))
            ->modalDescription(__('filament-saved-filter::saved-filter.actions.manage.description'))
            ->modalSubmitActionLabel(__('filament-saved-filter::saved-filter.actions.manage.submit'))
            ->icon('heroicon-o-adjustments-horizontal')
            ->keyBindings($plugin->getManageKeyBindings())
            ->slideOver()
            ->visible(fn () => $this->getEditableSavedFilters()->isNotEmpty())
            ->fillForm(fn () => [
                'saved_filters' => $this->getEditableSavedFilters()
                    ->map(fn (SavedFilter $filter) => $filter->only(['id', 'name', 'icon', 'color', 'is_default', 'is_public']))
                    ->all(),
            ])
            ->form([
                Repeater::make('saved_filters')
                    ->hiddenLabel()
                    ->schema([Hidden::make('id'), ...$this->getSavedFilterFormFields()])
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->addable(false)
                    ->reorderable()
                    ->collapsible(),
            ])
            ->action(function (array $data) use ($plugin): void {
                $editable = $this->getEditableSavedFilters()->keyBy('id');
                $kept = [];

                foreach (array_values($data['saved_filters'] ?? []) as $position => $item) {
                    if (! $filter = $editable->get((int) ($item['id'] ?? 0))) {
                        continue;
                    }

                    $filter->fill([
                        'name' => $item['name'],
                        'icon' => $item['icon'] ?? null,
                        'color' => $item['color'] ?? null,
                        'is_default' => (bool) ($item['is_default'] ?? false),
                        'order_column' => $position,
                    ]);

                    if ($plugin->userCanShare() && ! $filter->isPreset()) {
                        $filter->is_public = (bool) ($item['is_public'] ?? false);
                    }

                    $filter->save();
                    $kept[] = $filter->getKey();
                }

                $editable->except($kept)->each->delete();

                if ($this->activeSavedFilter && ! in_array($this->activeSavedFilter, $kept, true) && $editable->has($this->activeSavedFilter)) {
                    $this->activeSavedFilter = null;
                    $this->activeTab = 'all';
                }

                $this->flushSavedFilters();

                Notification::make()->success()->title(__('filament-saved-filter::saved-filter.actions.manage.notification'))->send();
            });
    }

    protected function getExportSavedFiltersAction(): Action
    {
        return Action::make('exportSavedFilters')
            ->label(__('filament-saved-filter::saved-filter.actions.export.label'))
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(fn () => $this->getSavedFilters()->isNotEmpty())
            ->action(function () {
                $json = json_encode(
                    ['saved_filters' => $this->getSavedFilters()->map->toPortableArray()->values()->all()],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                );

                return response()->streamDownload(fn () => print ($json), 'saved-filters.json', ['Content-Type' => 'application/json']);
            });
    }

    protected function getImportSavedFiltersAction(): Action
    {
        $plugin = SavedFilterPlugin::current();

        return Action::make('importSavedFilters')
            ->label(__('filament-saved-filter::saved-filter.actions.import.label'))
            ->icon('heroicon-o-arrow-up-tray')
            ->form([
                FileUpload::make('file')
                    ->label(__('filament-saved-filter::saved-filter.fields.file'))
                    ->acceptedFileTypes(['application/json', 'text/plain'])
                    ->maxSize(1024)
                    ->storeFiles(false)
                    ->required(),
            ])
            ->action(function (array $data) use ($plugin): void {
                $file = $data['file'] ?? null;
                $payload = $file instanceof TemporaryUploadedFile ? json_decode((string) $file->get(), true) : null;

                if (! is_array($payload)) {
                    Notification::make()->danger()->title(__('filament-saved-filter::saved-filter.actions.import.invalid'))->send();

                    return;
                }

                // Only this table's filters, owned by the importing user: no locked or (unless allowed) shared ones.
                $rows = collect($payload['saved_filters'] ?? $payload)
                    ->filter(fn ($row) => is_array($row) && ($row['resource'] ?? null) === $this->getSavedFilterResource() && $this->isImportableSavedFilterRow($row))
                    ->map(fn (array $row) => [...$row, 'is_public' => $plugin->userCanShare() && ($row['is_public'] ?? false)])
                    ->values()
                    ->all();

                $imported = $this->getSavedFilterModel()::import($rows, $this->getSavedFilterOwner(), $this->getSavedFilterTenant(), locked: false);

                $this->flushSavedFilters();

                Notification::make()->success()->title(__('filament-saved-filter::saved-filter.actions.import.notification', ['count' => $imported->count()]))->send();
            });
    }

    /**
     * A hand-edited file may carry any type: skip rows the columns can't hold instead of failing the whole import.
     *
     * @param  array<mixed>  $row
     */
    protected function isImportableSavedFilterRow(array $row): bool
    {
        if (! is_string($row['name'] ?? null) || mb_strlen($row['name']) > 255) {
            return false;
        }

        foreach ($row as $key => $value) {
            $valid = in_array($key, ['filters', 'toggled_columns'], true)
                ? $value === null || is_array($value)
                : $value === null || is_scalar($value);

            if (! $valid) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, mixed>
     */
    protected function getSavedFilterFormFields(): array
    {
        return [
            TextInput::make('name')
                ->label(__('filament-saved-filter::saved-filter.fields.name'))
                ->required()
                ->maxLength(255),
            Select::make('icon')
                ->label(__('filament-saved-filter::saved-filter.fields.icon'))
                ->options(array_combine(static::getSavedFilterIcons(), static::getSavedFilterIcons()))
                ->searchable(),
            Select::make('color')
                ->label(__('filament-saved-filter::saved-filter.fields.color'))
                ->options(collect(['primary', 'gray', 'info', 'success', 'warning', 'danger'])
                    ->mapWithKeys(fn (string $color) => [$color => __("filament-saved-filter::saved-filter.colors.{$color}")])
                    ->all()),
            Toggle::make('is_default')
                ->label(__('filament-saved-filter::saved-filter.fields.is_default')),
            Toggle::make('is_public')
                ->label(__('filament-saved-filter::saved-filter.fields.is_public'))
                ->visible(SavedFilterPlugin::current()->userCanShare()),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function getSavedFilterIcons(): array
    {
        return [
            'heroicon-o-star', 'heroicon-o-bookmark', 'heroicon-o-funnel', 'heroicon-o-eye', 'heroicon-o-clock',
            'heroicon-o-calendar', 'heroicon-o-check-circle', 'heroicon-o-exclamation-triangle', 'heroicon-o-fire',
            'heroicon-o-flag', 'heroicon-o-inbox', 'heroicon-o-tag', 'heroicon-o-user', 'heroicon-o-users',
            'heroicon-o-bolt', 'heroicon-o-archive-box', 'heroicon-o-document-text', 'heroicon-o-heart',
            'heroicon-o-sparkles', 'heroicon-o-light-bulb',
        ];
    }

    /** Imported rows may carry any string: an icon that doesn't exist would break the page. */
    protected function getSavedFilterIcon(SavedFilter $filter): ?string
    {
        if (blank($filter->icon)) {
            return null;
        }

        try {
            svg($filter->icon);

            return $filter->icon;
        } catch (Throwable) {
            return null;
        }
    }
}
