<?php

namespace JeffersonGoncalves\Filament\SavedFilter;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\Filament\SavedFilter\Concerns\HasSavedFilters;
use JeffersonGoncalves\SavedFilter\Contracts\BuildsSavedFilterReports;
use JeffersonGoncalves\SavedFilter\ReportFile;
use League\Csv\Writer as CsvWriter;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Builds a report from the ListRecords page in its context ({panel, page, tenant_id}), as the user, with the
 * page's Exporter. laravel-saved-filter decides when and to whom it goes.
 */
class FilamentReportBuilder implements BuildsSavedFilterReports
{
    public function build(Model $user, array $state, string $name, string $format, array $context): ?ReportFile
    {
        $panel = Filament::getPanels()[$context['panel'] ?? ''] ?? null;
        $page = $context['page'] ?? null;

        if (! $panel || ! $user instanceof Authenticatable || ! is_string($page) || ! class_exists($page)
            || ! in_array(HasSavedFilters::class, class_uses_recursive($page), true)) {
            return null;
        }

        Filament::setCurrentPanel($panel);
        Filament::auth()->setUser($user);

        // A tenant-aware panel without its tenant would run the page query across every tenant.
        if ($panel->hasTenancy()) {
            $key = $context['tenant_id'] ?? $context['tenant'] ?? null;
            $tenant = filled($key) ? $panel->getTenantModel()::query()->find($key) : null;

            // A user who left the tenant gets nothing from it anymore.
            if (! $tenant || ($user instanceof HasTenants && ! $user->canAccessTenant($tenant))) {
                return null;
            }

            Filament::setTenant($tenant, isQuiet: true);
        }

        // Same gate as opening the page: a user who lost access to the resource gets nothing.
        if (! $page::canAccess()) {
            return null;
        }

        $instance = app($page);
        $exporter = $instance->getSavedFilterExporter();

        if (! $exporter) {
            return null;
        }

        [$content, $count] = $this->export($exporter, $instance->getSavedFilterReportQuery($state), $format);

        return new ReportFile($content, str($name)->slug()->append('-', now()->format('Y-m-d'), '.', $format)->toString(), $count);
    }

    /**
     * @param  class-string<Exporter>  $exporter
     * @param  Builder<Model>  $query
     * @return array{string, int}
     */
    protected function export(string $exporter, Builder $query, string $format): array
    {
        $columnMap = collect($exporter::getColumns())
            ->filter(fn (ExportColumn $column): bool => $column->isEnabledByDefault())
            ->mapWithKeys(fn (ExportColumn $column): array => [$column->getName() => $column->getLabel() ?? $column->getName()])
            ->all();

        $row = new $exporter(new Export, $columnMap, []);
        $records = $exporter::modifyQuery($query)->lazy();
        $count = 0;

        if ($format === 'xlsx') {
            // ponytail: built in a temp file and mailed as an attachment; large views may exceed mail size limits.
            $path = (string) tempnam(sys_get_temp_dir(), 'saved-filter-report');
            $writer = new XlsxWriter;
            $writer->openToFile($path);
            $writer->addRow(Row::fromValues(array_values($columnMap)));

            foreach ($records as $record) {
                $writer->addRow(Row::fromValues($row($record)));
                $count++;
            }

            $writer->close();
            $content = (string) file_get_contents($path);
            @unlink($path);

            return [$content, $count];
        }

        $csv = CsvWriter::createFromString();
        $csv->setDelimiter($exporter::getCsvDelimiter());
        $csv->insertOne(array_values($columnMap));

        foreach ($records as $record) {
            $csv->insertOne($row($record));
            $count++;
        }

        return [$csv->toString(), $count];
    }
}
