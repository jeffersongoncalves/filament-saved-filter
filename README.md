<div class="filament-hidden">

![Filament Saved Filter](https://raw.githubusercontent.com/jeffersongoncalves/filament-saved-filter/3.x/art/jeffersongoncalves-filament-saved-filter.png)

</div>

# Filament Saved Filter

[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-support-FFDD00?style=flat-square&logo=buy-me-a-coffee&logoColor=black)](https://buymeacoffee.com/jeffersongoncalves)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/filament-saved-filter.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/filament-saved-filter)
[![PHP Version](https://img.shields.io/packagist/dependency-v/jeffersongoncalves/filament-saved-filter/php.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/filament-saved-filter)
[![Tests](https://img.shields.io/github/actions/workflow/status/jeffersongoncalves/filament-saved-filter/tests.yml?branch=3.x&label=tests&style=flat-square)](https://github.com/jeffersongoncalves/filament-saved-filter/actions?query=workflow%3ATests+branch%3A3.x)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/filament-saved-filter.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/filament-saved-filter)
[![License](https://img.shields.io/packagist/l/jeffersongoncalves/filament-saved-filter.svg?style=flat-square)](LICENSE.md)

Let your users **save the state of any Filament table** — applied filters, sort, search term and visible columns — under a name, and get back to it in one click. Saved filters show up as tabs above the table or in a "Views" dropdown, can be shared with the team, marked as default, managed in a slide-over and moved around as JSON.

Built on [jeffersongoncalves/laravel-saved-filter](https://github.com/jeffersongoncalves/laravel-saved-filter), which holds the model, the presets, the prune/export/import commands and the events.

## Features

- **Save current view** — filters, sort, search, per-column searches, toggled columns, records per page and grouping, with a name, icon and color.
- **Tabs or dropdown** — one tab per saved filter (plus "All"), or a list in the "Views" menu.
- **Default view** — opened automatically when the user lands on the table (a URL with filters still wins).
- **Team views** — share a filter with the other users of the tenant. Tabs show who else sees a view: **Preset**, **Shared**, or the colleague who created it.
- **Keyboard shortcuts** — `Ctrl/Cmd+Shift+S` saves the current view, `Ctrl/Cmd+Shift+M` opens "Manage views"; opt-in `Alt+1…9` and `Alt+]` / `Alt+[` to switch views. Shortcuts never fire while typing in a field.
- **Locked presets** — created in a seeder or by users you allow; everyone sees them, nobody changes them.
- **Manage views** — rename, reorder, change icon/color, set the default and delete, in a slide-over.
- **Update view** — overwrite the active filter with the current table state.
- **Modified badge + revert** — once the table drifts from the active view, the "Views" button shows *Modified* and **Revert changes** restores the saved state.
- **Export / import** — download the views as JSON and upload them in another environment (files up to 1 MB; rows with malformed fields are skipped, a view with the same name is overwritten).
- **Safe restores** — filters, columns and sort columns that no longer exist in the table are skipped, no exceptions.
- **Multi-tenancy** — scoped to the Filament tenant; database-per-tenant setups work as is.
- **Events** for auditing, **19 languages**, Filament 3, 4 and 5.

## Compatibility

| Branch | Filament | Package version |
|--------|----------|-----------------|
| 1.x | 3.x | `^1.0` |
| 2.x | 4.x | `^2.0` |
| 3.x | 5.x | `^3.0` |

## Installation

```bash
composer require jeffersongoncalves/filament-saved-filter:"^2.0"
php artisan vendor:publish --tag="saved-filter-migrations"
php artisan migrate
```

> **Upgrading?** Run the same `vendor:publish` + `migrate` again after each update: new columns (like `column_searches`) come as new migrations, and the ones you already ran are skipped.

Register the plugin in your panel provider:

```php
use JeffersonGoncalves\Filament\SavedFilter\SavedFilterPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            SavedFilterPlugin::make(),
        ]);
}
```

Add the trait to the list page of every resource that should have saved filters:

```php
use Filament\Resources\Pages\ListRecords;
use JeffersonGoncalves\Filament\SavedFilter\Concerns\HasSavedFilters;

class ListOrders extends ListRecords
{
    use HasSavedFilters;

    protected static string $resource = OrderResource::class;
}
```

A **Views** button appears in the table header. Once the user saves a view, the tabs show up.

## Configuration

```php
SavedFilterPlugin::make()
    ->dropdown()                                            // list the views in the "Views" menu instead of tabs
    ->canShare(fn () => auth()->user()->can('share-views')) // who may share views with the team (default: everyone)
    ->canManagePresets(fn () => auth()->user()->isAdmin())  // who may create/edit locked presets (default: nobody)
    ->scopedToTenant(false)                                 // ignore the Filament tenant (default: scoped)
    ->exportable(false)                                     // hide "Export views"
    ->importable(false)                                     // hide "Import views"
    ->keyBindings(save: ['mod+shift+s'], manage: null)      // change or disable (null) the action shortcuts
    ->tabShortcuts(),                                       // Alt+1…9 / Alt+] / Alt+[ to switch views (default: off)
```

With `tabShortcuts()`, `Alt+1` opens "All", `Alt+2` the first saved view and so on, `Alt+]` / `Alt+[` cycle through them. Physical keys are used, so it works on any keyboard layout and with the macOS Option key.

Records per page and grouping are only restored when the table still offers them (`->paginationPageOptions()`, `->groups()`); otherwise the table's defaults are used.

### Your own tabs

If the page already has tabs, the page's `getTabs()` wins over the trait's. Combine both by spreading the saved filter tabs:

```php
public function getTabs(): array
{
    return [
        'active' => Tab::make()->modifyQueryUsing(fn ($query) => $query->where('active', true)),
        ...$this->getSavedFilterTabs(),
    ];
}
```

### Your own `table()`

The trait adds its actions in `table()`. If your list page overrides `table()`, call the hook yourself:

```php
public function table(Table $table): Table
{
    return $this->configureSavedFilters(parent::table($table)->striped());
}
```

### Table key

Filters are stored per resource class. Override `getSavedFilterResource()` to share them between pages or to key them differently:

```php
public function getSavedFilterResource(): string
{
    return 'orders';
}
```

## Presets

Presets are saved filters without owner: every user of the tenant sees them and they are locked. Create them in a seeder:

```php
use JeffersonGoncalves\SavedFilter\Models\SavedFilter;

SavedFilter::query()->updateOrCreate(
    ['resource' => OrderResource::class, 'name' => 'Waiting for payment', 'owner_id' => null, 'tenant_id' => null],
    ['filters' => ['status' => ['value' => 'pending']], 'icon' => 'heroicon-o-clock', 'is_public' => true, 'is_locked' => true],
);
```

…or let users allowed by `canManagePresets()` tick **Save as a locked preset for everyone** when saving. Provision them between environments with `php artisan saved-filter:export` / `saved-filter:import` (see [laravel-saved-filter](https://github.com/jeffersongoncalves/laravel-saved-filter#export-and-import)).

## Multi-tenancy

With Filament tenancy the views are stored with the current tenant's key and listed per tenant. For database-per-tenant packages (`stancl/tenancy`) run the migration in the tenant databases; `tenant_id` stays null and the connection keeps each tenant apart.

## Auditing

`SavedFilterCreated`, `SavedFilterDeleted` and `SavedFilterApplied` (with the user) are dispatched from `JeffersonGoncalves\SavedFilter\Events` — listen to them to feed `spatie/laravel-activitylog` or any audit log.

## Translations

The UI ships in 19 languages: ar, az, de, en, es, fa, fr, hi, it, ja, nl, pl, pt, pt_BR, ru, tr, uk, uz and zh_CN. Publish them to change any string:

```bash
php artisan vendor:publish --tag=filament-saved-filter-translations
```

Missing your language? Copy `resources/lang/en/saved-filter.php` and open a pull request.

## Roadmap

- Save the column order (Filament 4/5 reorderable columns).
- Saved filters for relation managers and custom table pages.
- Per-view badge with the record count.

## Requirements

- PHP 8.2 or higher
- Filament 4.x

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Jefferson Gonçalves](https://github.com/jeffersongoncalves)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
