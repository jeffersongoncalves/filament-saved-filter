## Filament Saved Filter

Saved table views for Filament ListRecords pages (filters, sort, search, toggled columns) as tabs or a dropdown, on top of jeffersongoncalves/laravel-saved-filter.

### Installation

@verbatim
<code-snippet name="Install the plugin" lang="bash">
composer require jeffersongoncalves/filament-saved-filter:"^3.0"
php artisan vendor:publish --tag="saved-filter-migrations"
php artisan migrate
</code-snippet>
@endverbatim

### Register Plugin

@verbatim
<code-snippet name="Register in PanelProvider and use the trait" lang="php">
use JeffersonGoncalves\Filament\SavedFilter\Concerns\HasSavedFilters;
use JeffersonGoncalves\Filament\SavedFilter\SavedFilterPlugin;

$panel->plugins([
    SavedFilterPlugin::make()
        // ->dropdown()
        // ->canShare(fn () => true)
        // ->canManagePresets(fn () => auth()->user()->isAdmin())
        // ->keyBindings(save: ['mod+shift+s'], manage: ['mod+shift+m'])
        // ->tabShortcuts()
        ,
]);

class ListOrders extends ListRecords
{
    use HasSavedFilters;
}
</code-snippet>
@endverbatim

### Architecture
- `HasSavedFilters` overrides `table()` (pushes the "Views" header action group), `getTabs()`, `loadDefaultActiveTab()` and `updatedActiveTab()` of the list page
- Pages with their own `getTabs()` spread `...$this->getSavedFilterTabs()`; pages with their own `table()` call `$this->configureSavedFilters($table)`
- Filters are keyed by `getSavedFilterResource()` (the resource class by default) and the Filament tenant
- Model: `JeffersonGoncalves\SavedFilter\Models\SavedFilter`; translations under `filament-saved-filter::saved-filter.*`

### Best Practices
- Seed locked presets (`owner_id` null, `is_locked` true) for the views every user needs
- Restrict sharing and preset management with `canShare()` / `canManagePresets()`
- Schedule `saved-filter:prune` to drop filters of deleted users and removed resources
