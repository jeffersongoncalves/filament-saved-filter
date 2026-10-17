<?php

namespace JeffersonGoncalves\Filament\SavedFilter;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SavedFilterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-saved-filter')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations();
    }
}
