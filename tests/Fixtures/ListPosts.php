<?php

namespace JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures;

use Filament\Resources\Pages\ListRecords;
use JeffersonGoncalves\Filament\SavedFilter\Concerns\HasSavedFilters;

class ListPosts extends ListRecords
{
    use HasSavedFilters;

    protected static string $resource = PostResource::class;

    public function getSavedFilterExporter(): ?string
    {
        return PostExporter::class;
    }
}
