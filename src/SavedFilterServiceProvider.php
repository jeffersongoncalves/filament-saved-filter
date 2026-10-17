<?php

namespace JeffersonGoncalves\Filament\SavedFilter;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use JeffersonGoncalves\Filament\SavedFilter\Concerns\HasSavedFilters;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SavedFilterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-saved-filter')
            ->hasTranslations()
            ->hasViews();
    }

    public function packageBooted(): void
    {
        // Only list pages that use the trait get the Alt+N listener, and only when the panel turned it on.
        FilamentView::registerRenderHook(
            PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE,
            fn (array $scopes = []): string => SavedFilterPlugin::current()->hasTabShortcuts()
                && collect($scopes)->contains(fn (string $scope): bool => class_exists($scope) && in_array(HasSavedFilters::class, class_uses_recursive($scope), true))
                    ? view('filament-saved-filter::tab-shortcuts')->render()
                    : '',
        );
    }
}
