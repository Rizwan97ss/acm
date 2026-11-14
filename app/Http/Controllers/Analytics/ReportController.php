<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\CsvExporter;
use App\Services\Reports\ReportCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV exports. Every export is audited and limited to one year of data per file.
 */
class ReportController extends Controller
{
    public function __construct(private ReportCatalog $catalog) {}

    public function index(Request $request): Response
    {
        Gate::authorize('reports.export');

        return Inertia::render('analytics/reports', [
            'reports' => $this->catalog->availableTo($request->user()),
            'defaults' => ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()],
        ]);
    }

    public function export(Request $request, string $report, CsvExporter $exporter, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize('reports.export');
        abort_unless(isset(ReportCatalog::REPORTS[$report]), 404);
        Gate::authorize(ReportCatalog::REPORTS[$report]['permission']);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $to = CarbonImmutable::parse($validated['to'] ?? now()->toDateString());
        $from = CarbonImmutable::parse($validated['from'] ?? $to->startOfMonth()->toDateString());

        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['from' => __('Exports are limited to one year at a time.')]);
        }

        [$headings, $rows] = $this->catalog->build($report, $from, $to);

        $audit->log('report.exported', null, [], array_filter([
            'report' => $report,
            'from' => ReportCatalog::REPORTS[$report]['dated'] ? $from->toDateString() : null,
            'to' => ReportCatalog::REPORTS[$report]['dated'] ? $to->toDateString() : null,
        ]));

        $suffix = ReportCatalog::REPORTS[$report]['dated'] ? "{$from->toDateString()}-to-{$to->toDateString()}" : now()->toDateString();

        return $exporter->download("{$report}-{$suffix}.csv", $headings, $rows);
    }
}
