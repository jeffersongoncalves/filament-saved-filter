<?php

namespace JeffersonGoncalves\Filament\SavedFilter\Tests\Fixtures;

use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use JeffersonGoncalves\Filament\SavedFilter\Concerns\HasSavedFilters;

/** A custom page with its own table: no tabs, no resource. */
class PostsReport extends Page implements HasTable
{
    // Before InteractsWithTable on purpose (Pint's order): the trait must not depend on the order of the traits.
    use HasSavedFilters;
    use InteractsWithTable;

    protected static string $view = 'posts-report';

    public function table(Table $table): Table
    {
        return $this->configureSavedFilters($table
            ->query(Post::query())
            ->columns([TextColumn::make('title')])
            ->filters([SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published'])]));
    }

    public function getSavedFilterExporter(): ?string
    {
        return PostExporter::class;
    }
}
