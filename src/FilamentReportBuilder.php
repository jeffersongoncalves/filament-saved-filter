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
use Throwable;

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
        // A queue worker keeps the previous job's tenant: never let it leak into this report.
        Filament::setTenant(null, isQuiet: true);

        // A tenant-aware panel without its tenant would run the page query across every tenant.
        if ($panel->hasTenancy()) {
            $key = $context['tenant_id'] ?? $context['tenant'] ?? null;
            $tenant = filled($key) ? $panel->getTenantModel()::query()->find($key) : null;

            // A user who left the tenant gets nothing from it anymore.
            if (! $tenant || ($user instanceof HasTenants && ! $user->canAccessTenant($tenant))) {
                return null;
            }

            Filament::setTenant($tenant, isQuiet: true);

            // Filament adds the tenant scope in Panel::boot(), from its request middleware. Outside a request only
            // this part is needed: the rest of boot() (render hooks, assets) would pile up in a queue worker.
            $page::getResource()::registerTenancyModelGlobalScope($panel);
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

        if ($count === 0 && ($context['skip_empty'] ?? false)) {
            return null;
        }

        return new ReportFile(
            $content,
            str($name)->slug()->append('-', now()->format('Y-m-d'), '.', $format)->toString(),
            $count,
            $this->getUrl($page, $context),
        );
    }

    /**
     * The page in the report's panel and tenant, on the saved view's tab when the panel shows views as tabs (the
     * dropdown keeps no view in the URL). Used for the email's "open the view" button.
     *
     * @param  class-string  $page
     * @param  array<string, mixed>  $context
     */
    protected function getUrl(string $page, array $context): ?string
    {
        $view = $context['saved_filter_id'] ?? null;
        $parameters = filled($view) && SavedFilterPlugin::current()->getDisplayMode() === SavedFilterPlugin::TABS
            ? ['tab' => "saved-{$view}"]
            : [];

        try {
            return $page::getUrl($parameters, panel: Filament::getCurrentPanel()?->getId(), tenant: Filament::getTenant());
        } catch (Throwable) {
            return null;
        }
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

        $exporterRow = new $exporter(new Export, $columnMap, []);
        $row = fn (Model $record): array => array_map($this->neutralizeFormula(...), $exporterRow($record));
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

        $csv = CsvWriter::fromString();
        $csv->setDelimiter($exporter::getCsvDelimiter());
        $csv->insertOne(array_values($columnMap));

        foreach ($records as $record) {
            $csv->insertOne($row($record));
            $count++;
        }

        return [$csv->toString(), $count];
    }

    /**
     * Spreadsheets run a cell starting with = + - @ (or a tab/CR before one) as a formula. Records are user content
     * and the file lands in a colleague's inbox, so such text is prefixed with ' (numbers like -5 are left alone).
     */
    protected function neutralizeFormula(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && ! is_numeric($value) && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
