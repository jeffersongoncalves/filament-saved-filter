<?php

namespace JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use JeffersonGoncalves\Filament\SavedFilter\SavedFilterPlugin;

/** Same resource, scoped to a Team: reports must never leave the subscriber's team. */
class TenantPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('tenant')
            ->path('tenant')
            ->tenant(Team::class)
            ->resources([PostResource::class])
            ->plugin(SavedFilterPlugin::make());
    }
}
