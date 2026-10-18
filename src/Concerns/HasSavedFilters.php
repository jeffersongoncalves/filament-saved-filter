<?php

namespace JeffersonGoncalves\Filament\SavedFilter\Concerns;

use Carbon\CarbonInterface;
use Filament\Actions\Exports\Exporter;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
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
use JeffersonGoncalves\SavedFilter\Jobs\SendSavedFilterReport;
use JeffersonGoncalves\SavedFilter\Models\SavedFilter;
use JeffersonGoncalves\SavedFilter\Models\SavedFilterReport;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Save, share and restore the table state (filters, sort, search, toggled columns) of a ListRecords page.
 */
trait HasSavedFilters
{
    /** In the URL as ?view=, so a shared link opens the view in the dropdown mode too. */
    #[Url(as: 'view')]
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
        $hasTableState = filled($this->tableFilters) || filled($this->tableSearch) || filled($this->tableSortColumn);
        $tabs = SavedFilterPlugin::current()->getDisplayMode() === SavedFilterPlugin::TABS;

        if ($this->activeSavedFilter !== null && blank($this->activeTab)) {
            // ?view= from a shared link: a view this user doesn't see is dropped; next to a table state from the
            // same link the view only stays marked (shown as Modified), otherwise it's applied.
            $view = $this->findSavedFilter($this->activeSavedFilter);
            $this->activeSavedFilter = $view?->getKey();
            $this->pendingSavedFilter = $view && ! $hasTableState ? $view->getKey() : null;
            $this->activeTab = $view && $tabs ? "saved-{$view->getKey()}" : null;
        } elseif (blank($this->activeTab) && ! $hasTableState && ($default = $this->getDefaultSavedFilter())) {
            $this->pendingSavedFilter = $default->getKey();
            $this->activeTab = $tabs ? "saved-{$default->getKey()}" : null;
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
        $this->applySavedFilterState($filter->only(SavedFilter::STATE));

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

        $searchable = collect($table->getColumns())->filter->isIndividuallySearchable()->keys()->all();
        $this->tableColumnSearches = [];

        foreach (Arr::only(Arr::dot($state['column_searches'] ?? []), $searchable) as $column => $search) {
            Arr::set($this->tableColumnSearches, $column, (string) $search);
        }

        $this->updatedTableColumnSearches();

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
     * @return array{filters: array<string, mixed>, sort_column: ?string, sort_direction: ?string, search: ?string, column_searches: ?array<string, mixed>, toggled_columns: array<string, bool>, records_per_page: ?string, grouping: ?string, grouping_direction: ?string}
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
            'column_searches' => $this->getSavedFilterColumnSearches(),
            'toggled_columns' => $toggled,
            'records_per_page' => $this->getTable()->isPaginated() && filled($this->tableRecordsPerPage) ? (string) $this->tableRecordsPerPage : null,
            'grouping' => filled($this->tableGrouping) ? $this->tableGrouping : null,
            'grouping_direction' => filled($this->tableGrouping) ? ($this->getTableGroupingDirection() ?? 'asc') : null,
        ];
    }

    /**
     * The filled per-column searches, nested again for dotted names (author.name); null when there are none.
     *
     * @return array<string, mixed>|null
     */
    protected function getSavedFilterColumnSearches(): ?array
    {
        $searches = [];

        foreach (Arr::dot($this->tableColumnSearches) as $column => $search) {
            if (filled($search)) {
                Arr::set($searches, (string) $column, (string) $search);
            }
        }

        return $searches ?: null;
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
                $this->getEmailSavedFilterAction(),
                $this->getSavedFilterReportAction(),
                $this->getManageSavedFilterReportsAction(),
                $this->getExportSavedFiltersAction(),
                $this->getImportSavedFiltersAction(),
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

    /**
     * The Exporter that builds the email reports of this page; null hides them.
     *
     * @return class-string<Exporter>|null
     */
    public function getSavedFilterExporter(): ?string
    {
        return null;
    }

    /**
     * The records of a state, on a page built outside a request (email reports).
     *
     * @param  array<string, mixed>  $state  SavedFilter attributes
     * @return Builder<Model>
     */
    public function getSavedFilterReportQuery(array $state): Builder
    {
        $this->bootedInteractsWithTable();
        $this->applySavedFilterState($state);

        return $this->getTableQueryForExport();
    }

    /**
     * What FilamentReportBuilder needs to rebuild this page. The tenant is kept even when the filters aren't
     * scoped to it (scopedToTenant(false)), so the report never runs on a tenant-aware panel without one.
     *
     * @return array{panel: string, page: class-string, tenant: mixed}
     */
    protected function getSavedFilterReportContext(): array
    {
        return ['panel' => Filament::getCurrentPanel()->getId(), 'page' => static::class, 'tenant' => Filament::getTenant()?->getKey()];
    }

    protected function hasSavedFilterReports(): bool
    {
        return SavedFilterPlugin::current()->hasEmailReports() && filled($this->getSavedFilterExporter());
    }

    protected function getEmailSavedFilterAction(): Action
    {
        return Action::make('emailSavedFilter')
            ->label(__('filament-saved-filter::saved-filter.actions.email.label'))
            ->icon('heroicon-o-envelope')
            ->modalWidth('sm')
            ->visible(fn () => $this->hasSavedFilterReports() && filled($this->getSavedFilterOwner()?->getAttribute('email')))
            ->form([$this->getSavedFilterReportFormatField()])
            ->action(function (array $data): void {
                $owner = $this->getSavedFilterOwner();

                if (! $owner || ! $this->hasSavedFilterReports()) {
                    return;
                }

                $active = $this->activeSavedFilter ? $this->findSavedFilter($this->activeSavedFilter) : null;

                SendSavedFilterReport::dispatch(
                    $owner,
                    $this->getCurrentSavedFilterState(),
                    $active->name ?? static::getResource()::getPluralModelLabel(),
                    $data['format'] === 'xlsx' ? 'xlsx' : 'csv',
                    // The email links to the view only while the table still shows it as saved.
                    [...$this->getSavedFilterReportContext(), 'saved_filter_id' => $active && ! $this->isSavedFilterModified() ? $active->getKey() : null],
                );

                Notification::make()->success()->title(__('filament-saved-filter::saved-filter.actions.email.notification'))->send();
            });
    }

    /**
     * Each user turns the email report of a view they see on or off for themselves, shared views included.
     * Opens on the active view, or the first one from "All"; picking another view loads its settings.
     */
    protected function getSavedFilterReportAction(): Action
    {
        $settings = function (?int $id): array {
            $owner = $this->getSavedFilterOwner();
            $existing = $id && $owner ? $this->findSavedFilter($id)?->reports()->forUser($owner)->first() : null;

            return $existing
                ? [...$existing->only(['is_active', 'frequency', 'day', 'time', 'format']), 'skip_empty' => (bool) ($existing->context['skip_empty'] ?? false)]
                : ['is_active' => true, 'frequency' => SavedFilterReport::WEEKLY, 'day' => 1, 'time' => '08:00', 'format' => 'csv', 'skip_empty' => false];
        };

        return Action::make('savedFilterReport')
            ->label(__('filament-saved-filter::saved-filter.actions.report.label'))
            ->modalDescription(__('filament-saved-filter::saved-filter.actions.report.description'))
            ->icon('heroicon-o-calendar-days')
            ->modalWidth('md')
            ->visible(fn () => $this->hasSavedFilterReports() && $this->getSavedFilters()->isNotEmpty() && filled($this->getSavedFilterOwner()?->getAttribute('email')))
            ->fillForm(function () use ($settings): array {
                $id = $this->activeSavedFilter && $this->findSavedFilter($this->activeSavedFilter)
                    ? $this->activeSavedFilter
                    : $this->getSavedFilters()->first()?->getKey();

                return ['saved_filter_id' => $id, ...$settings($id)];
            })
            ->form([
                Select::make('saved_filter_id')
                    ->label(__('filament-saved-filter::saved-filter.fields.view'))
                    ->options(fn (): array => $this->getSavedFilters()->pluck('name', 'id')->all())
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live()
                    ->helperText(fn ($get): ?string => $this->describeSavedFilterReport(filled($get('saved_filter_id')) ? (int) $get('saved_filter_id') : null))
                    ->afterStateUpdated(function ($state, $set) use ($settings): void {
                        foreach ($settings(filled($state) ? (int) $state : null) as $field => $value) {
                            $set($field, $value);
                        }
                    }),
                Toggle::make('is_active')
                    ->label(__('filament-saved-filter::saved-filter.fields.report_active')),
                Select::make('frequency')
                    ->label(__('filament-saved-filter::saved-filter.fields.frequency'))
                    ->options(collect([SavedFilterReport::DAILY, SavedFilterReport::WEEKLY, SavedFilterReport::MONTHLY])
                        ->mapWithKeys(fn (string $frequency) => [$frequency => __("filament-saved-filter::saved-filter.frequencies.{$frequency}")])
                        ->all())
                    ->required()
                    ->live(),
                Select::make('day')
                    ->label(__('filament-saved-filter::saved-filter.fields.day'))
                    ->options(fn ($get): array => $get('frequency') === SavedFilterReport::WEEKLY
                        ? collect(range(1, 7))->mapWithKeys(fn (int $day) => [$day => now()->startOfWeek(CarbonInterface::MONDAY)->addDays($day - 1)->translatedFormat('l')])->all()
                        : array_combine(range(1, 28), range(1, 28)))
                    ->visible(fn ($get): bool => $get('frequency') !== SavedFilterReport::DAILY)
                    ->required(),
                TimePicker::make('time')
                    ->label(__('filament-saved-filter::saved-filter.fields.time'))
                    ->seconds(false)
                    ->required(),
                $this->getSavedFilterReportFormatField(),
                Toggle::make('skip_empty')
                    ->label(__('filament-saved-filter::saved-filter.fields.skip_empty')),
            ])
            ->action(function (array $data): void {
                // Only a view this user sees: the id comes from the browser.
                $filter = $this->findSavedFilter((int) ($data['saved_filter_id'] ?? 0));
                $owner = $this->getSavedFilterOwner();

                if (! $filter || ! $owner || ! $this->hasSavedFilterReports()) {
                    return;
                }

                $filter->reports()->updateOrCreate([
                    'user_type' => $owner->getMorphClass(),
                    'user_id' => (string) $owner->getKey(),
                ], [
                    'context' => [...$this->getSavedFilterReportContext(), 'saved_filter_id' => $filter->getKey(), 'skip_empty' => (bool) ($data['skip_empty'] ?? false)],
                    'frequency' => $data['frequency'],
                    'day' => $data['frequency'] === SavedFilterReport::DAILY ? null : (int) $data['day'],
                    'time' => substr((string) $data['time'], 0, 5),
                    'format' => $data['format'] === 'xlsx' ? 'xlsx' : 'csv',
                    'is_active' => (bool) ($data['is_active'] ?? false),
                ]);

                Notification::make()->success()->title(__('filament-saved-filter::saved-filter.actions.report.notification'))->send();
            });
    }

    /**
     * This user's email reports on every table of the current tenant, to turn them on or off (or drop them) in one place.
     *
     * @return Collection<int, SavedFilterReport>
     */
    protected function getUserSavedFilterReports(): Collection
    {
        $owner = $this->getSavedFilterOwner();

        if (! $owner) {
            return new Collection;
        }

        return SavedFilterReport::query()
            ->forUser($owner)
            ->whereHas('savedFilter', fn (Builder $query) => $query->forTenant($this->getSavedFilterTenant()))
            ->with('savedFilter')
            ->orderBy('id')
            ->get();
    }

    protected function getManageSavedFilterReportsAction(): Action
    {
        $label = function (SavedFilterReport $report): string {
            $resource = (string) $report->savedFilter?->resource;
            $table = class_exists($resource) && method_exists($resource, 'getPluralModelLabel') ? $resource::getPluralModelLabel() : $resource;

            return "{$table} · {$report->savedFilter?->name} — {$report->describeSchedule()}";
        };

        return Action::make('manageSavedFilterReports')
            ->label(__('filament-saved-filter::saved-filter.actions.my_reports.label'))
            ->modalHeading(__('filament-saved-filter::saved-filter.actions.my_reports.label'))
            ->modalDescription(__('filament-saved-filter::saved-filter.actions.my_reports.description'))
            ->icon('heroicon-o-inbox-stack')
            ->slideOver()
            ->visible(fn () => $this->hasSavedFilterReports() && $this->getUserSavedFilterReports()->isNotEmpty())
            ->fillForm(fn () => [
                'reports' => $this->getUserSavedFilterReports()
                    ->map(fn (SavedFilterReport $report) => ['id' => $report->getKey(), 'label' => $label($report), 'is_active' => $report->is_active])
                    ->all(),
            ])
            ->schema([
                Repeater::make('reports')
                    ->hiddenLabel()
                    ->schema([
                        Hidden::make('id'),
                        Hidden::make('label'),
                        Toggle::make('is_active')->label(__('filament-saved-filter::saved-filter.fields.report_active')),
                    ])
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                    ->addable(false)
                    ->reorderable(false),
            ])
            ->action(function (array $data): void {
                // Ids come from the browser: only this user's reports are touched.
                $reports = $this->getUserSavedFilterReports()->keyBy('id');
                $kept = [];

                foreach ($data['reports'] ?? [] as $item) {
                    if ($report = $reports->get((int) ($item['id'] ?? 0))) {
                        $report->update(['is_active' => (bool) ($item['is_active'] ?? false)]);
                        $kept[] = $report->getKey();
                    }
                }

                $reports->except($kept)->each->delete();

                Notification::make()->success()->title(__('filament-saved-filter::saved-filter.actions.my_reports.notification'))->send();
            });
    }

    /** What this user has scheduled for a view right now, e.g. "Sent every Monday at 08:00."; null when nothing is on. */
    protected function describeSavedFilterReport(?int $id): ?string
    {
        $owner = $this->getSavedFilterOwner();

        return $id && $owner
            ? $this->findSavedFilter($id)?->reports()->forUser($owner)->active()->first()?->describeSchedule()
            : null;
    }

    protected function getSavedFilterReportFormatField(): Select
    {
        return Select::make('format')
            ->label(__('filament-saved-filter::saved-filter.fields.format'))
            ->options(['csv' => 'CSV', 'xlsx' => 'Excel (XLSX)'])
            ->default('csv')
            ->selectablePlaceholder(false)
            ->required();
    }

    protected function getExportSavedFiltersAction(): Action
    {
        return Action::make('exportSavedFilters')
            ->label(__('filament-saved-filter::saved-filter.actions.export.label'))
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(fn () => SavedFilterPlugin::current()->isExportable() && $this->getSavedFilters()->isNotEmpty())
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
            ->visible(fn () => SavedFilterPlugin::current()->isImportable())
            ->form([
                FileUpload::make('file')
                    ->label(__('filament-saved-filter::saved-filter.fields.file'))
                    ->acceptedFileTypes(['application/json', 'text/plain'])
                    ->maxSize(1024)
                    ->storeFiles(false)
                    ->required(),
                Select::make('on_conflict')
                    ->label(__('filament-saved-filter::saved-filter.fields.on_conflict'))
                    ->options(collect(['overwrite', 'skip', 'copy'])
                        ->mapWithKeys(fn (string $strategy) => [$strategy => __("filament-saved-filter::saved-filter.conflicts.{$strategy}")])
                        ->all())
                    ->default('overwrite')
                    ->selectablePlaceholder(false)
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

                $rows = $this->resolveSavedFilterImportConflicts($rows, (string) ($data['on_conflict'] ?? 'overwrite'));

                $imported = $this->getSavedFilterModel()::import($rows, $this->getSavedFilterOwner(), $this->getSavedFilterTenant(), locked: false);

                $this->flushSavedFilters();

                Notification::make()->success()->title(__('filament-saved-filter::saved-filter.actions.import.notification', ['count' => $imported->count()]))->send();
            });
    }

    /**
     * Rows named like one of the user's views on this table: "overwrite" leaves them to SavedFilter::import() (it
     * matches by name), "skip" drops them, "copy" renames them "Name (2)", "Name (3)"…
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function resolveSavedFilterImportConflicts(array $rows, string $strategy): array
    {
        if (! in_array($strategy, ['skip', 'copy'], true)) {
            return $rows;
        }

        $taken = $this->getSavedFilterQuery()->ownedBy($this->getSavedFilterOwner())->pluck('name')->flip()->all();
        $resolved = [];

        foreach ($rows as $row) {
            $name = (string) $row['name'];

            if (isset($taken[$name])) {
                if ($strategy === 'skip') {
                    continue;
                }

                for ($n = 2; isset($taken[$name]); $n++) {
                    $suffix = " ({$n})";
                    $name = mb_substr((string) $row['name'], 0, 255 - mb_strlen($suffix)).$suffix;
                }
            }

            $taken[$name] = true;
            $resolved[] = [...$row, 'name' => $name];
        }

        return $resolved;
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
            $valid = in_array($key, ['filters', 'column_searches', 'toggled_columns'], true)
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
