<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Analytics\InsightsService;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trends, early warnings and forecasts computed by the Python analytics service. Each
 * section is a deferred prop, shown only with view access to its underlying module.
 */
class InsightsController extends Controller
{
    public function __construct(private InsightsService $insights) {}

    public function index(Request $request, AnalyticsClient $client): Response
    {
        Gate::authorize('analytics.view');

        $user = $request->user();
        $canAttendance = $user->can('attendance.view');
        $canExams = $user->can('exams.view');
        $canFinance = $user->can('accounting.view');

        return Inertia::render('analytics/insights', [
            'configured' => $client->isConfigured(),
            'sections' => ['attendance' => $canAttendance, 'performance' => $canExams, 'finance' => $canFinance],
            'can' => ['export' => $user->can('analytics.export') && ($canAttendance || $canFinance)],
            'attendance' => $canAttendance ? Inertia::defer(fn (): array => $this->insights->attendanceTrends(), 'attendance') : null,
            'atRisk' => $canAttendance ? Inertia::defer(fn (): array => $this->insights->atRisk(), 'attendance') : null,
            'performance' => $canExams ? Inertia::defer(fn (): array => $this->insights->performance(), 'performance') : null,
            'finance' => $canFinance ? Inertia::defer(fn (): array => $this->insights->finance(), 'finance') : null,
        ]);
    }

    /**
     * Downloads an interactive Plotly report for the sections the user may see.
     */
    public function report(Request $request, CurrentSchool $currentSchool, AuditLogger $audit): HttpResponse|RedirectResponse
    {
        Gate::authorize('analytics.export');

        $user = $request->user();
        $sections = ['attendance' => $user->can('attendance.view'), 'finance' => $user->can('accounting.view')];
        abort_if(! $sections['attendance'] && ! $sections['finance'], 403);

        $schoolName = (string) ($currentSchool->get()->name ?? config('app.name'));
        $html = $this->insights->htmlReport("{$schoolName} — Insights report", $sections);

        if ($html === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('The analytics service is unavailable. Try again shortly.')]);

            return back();
        }

        $audit->log('analytics.report_exported', null, [], ['sections' => array_keys(array_filter($sections))]);

        $filename = Str::slug($schoolName).'-insights-'.now()->format('Y-m-d').'.html';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }
}
