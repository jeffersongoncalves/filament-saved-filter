<?php

namespace JeffersonGoncalves\Filament\SavedFilter;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;

class SavedFilterPlugin implements Plugin
{
    public const TABS = 'tabs';

    public const DROPDOWN = 'dropdown';

    protected string $displayMode = self::TABS;

    protected bool|Closure $canShare = true;

    protected bool|Closure $canManagePresets = false;

    protected bool $scopedToTenant = true;

    protected bool $exportable = true;

    protected bool $importable = true;

    public function getId(): string
    {
        return 'filament-saved-filter';
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    /** The panel's instance when registered, otherwise the defaults. */
    public static function current(): static
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        $id = app(static::class)->getId();

        /** @var static $plugin */
        $plugin = $panel?->hasPlugin($id) ? $panel->getPlugin($id) : static::make();

        return $plugin;
    }

    /** Show the saved filters as tabs above the table (default). */
    public function tabs(): static
    {
        $this->displayMode = self::TABS;

        return $this;
    }

    /** Show the saved filters in the "Views" dropdown of the table header. */
    public function dropdown(): static
    {
        $this->displayMode = self::DROPDOWN;

        return $this;
    }

    public function getDisplayMode(): string
    {
        return $this->displayMode;
    }

    /** Who may share a filter with the other users of the tenant. */
    public function canShare(bool|Closure $condition = true): static
    {
        $this->canShare = $condition;

        return $this;
    }

    public function userCanShare(): bool
    {
        return (bool) value($this->canShare);
    }

    /** Who may create, edit and delete presets (locked filters every user sees). */
    public function canManagePresets(bool|Closure $condition = true): static
    {
        $this->canManagePresets = $condition;

        return $this;
    }

    public function userCanManagePresets(): bool
    {
        return (bool) value($this->canManagePresets);
    }

    /** Store and list the filters per Filament tenant. */
    public function scopedToTenant(bool $condition = true): static
    {
        $this->scopedToTenant = $condition;

        return $this;
    }

    public function isScopedToTenant(): bool
    {
        return $this->scopedToTenant;
    }

    public function exportable(bool $condition = true): static
    {
        $this->exportable = $condition;

        return $this;
    }

    public function isExportable(): bool
    {
        return $this->exportable;
    }

    public function importable(bool $condition = true): static
    {
        $this->importable = $condition;

        return $this;
    }

    public function isImportable(): bool
    {
        return $this->importable;
    }
}
