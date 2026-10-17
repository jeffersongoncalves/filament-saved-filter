# Changelog

All notable changes to this project will be documented in this file.

## 1.2.0 - 2026-10-17

### What's new

- **Per-column searches** are saved and restored with each view. Searches on columns that are no longer individually searchable are ignored.
- **Modified badge + revert**: once the table drifts from the active view, the Views button shows *Modified* and **Revert changes** restores the saved state.
- **Safer JSON import**: files are capped at 1 MB and rows with malformed fields are skipped instead of failing the whole import.
- Tests now run on SQLite, MySQL 8.4 and PostgreSQL 17.

### Upgrading

Requires `jeffersongoncalves/laravel-saved-filter` ^1.3. Publish and migrate again to add the `column_searches` column (migrations already run are skipped):

```bash
php artisan vendor:publish --tag="saved-filter-migrations"
php artisan migrate

```
## 1.1.0 - 2026-10-17

- Views also save records per page and grouping (needs laravel-saved-filter ^1.1: run `php artisan migrate`).
- Tabs and dropdown items show who else sees a view: Preset, Shared, or the colleague who created it.
- Keyboard shortcuts: Ctrl/Cmd+Shift+S saves the current view, Ctrl/Cmd+Shift+M opens Manage views (`->keyBindings()` to change or disable).
- Opt-in `->tabShortcuts()`: Alt+1…9 and Alt+] / Alt+[ to switch views, ignored while typing.
- Tests run on Laravel 13.

## 1.0.1 - 2026-10-17

Manage views slide-over: description text and a "Save changes" submit button, in all 19 languages.

## 1.0.0 - 2026-10-17

First release for Filament 3.x.

## [Unreleased]
