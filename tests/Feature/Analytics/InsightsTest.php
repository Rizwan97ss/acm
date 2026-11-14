<?php

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamPaper;
use App\Models\Invoice;
use App\Models\Mark;
use App\Services\Academics\GradeScaleService;
use App\Services\Analytics\AnalyticsClient;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const ANALYTICS_SECRET = 'a-test-shared-secret-of-at-least-32-chars';

beforeEach(function () {
    config(['services.analytics.url' => 'http://analytics.test', 'services.analytics.secret' => ANALYTICS_SECRET]);
});

function analyticsSchool(): array
{
    $setup = teachingSchool();

    foreach ([AttendanceStatus::Present, AttendanceStatus::Absent, AttendanceStatus::Absent] as $daysAgo => $status) {
        AttendanceRecord::factory()->create([
            'school_id' => $setup['school']->id,
            'academic_year_id' => $setup['year']->id,
            'section_id' => $setup['section']->id,
            'student_id' => $setup['student']->id,
            'date' => now()->subDays($daysAgo + 1)->toDateString(),
            'status' => $status,
        ]);
    }

    return $setup;
}

function fakeAnalytics(): void
{
    Http::fake([
        'analytics.test/v1/attendance/trends' => Http::response(['daily' => [], 'weekdays' => [], 'summary' => ['overall_rate' => 33.33, 'school_days' => 3]]),
        'analytics.test/v1/attendance/at-risk' => fn (HttpRequest $request) => Http::response([
            'students' => [['student_id' => $request['students'][0]['student_id'], 'name' => null, 'section' => 'X A', 'rate' => 33.33, 'z_score' => 0, 'risk_score' => 90, 'reasons' => ['Attendance below 75%']]],
            'summary' => ['total' => 1, 'at_risk' => 1, 'mean_rate' => 33.33],
        ]),
        'analytics.test/v1/finance/forecast' => Http::response(['history' => [], 'forecast' => ['collected' => [], 'expenses' => []], 'aging' => []]),
        'analytics.test/v1/reports/html' => Http::response('<!doctype html><title>Report</title>'),
    ]);
}

test('insights requires analytics.view', function () {
    $setup = analyticsSchool();

    $this->actingAs(userWithPermissions(['attendance.view'], $setup['school']))
        ->get(route('analytics.insights'))
        ->assertForbidden();
});

test('insights sends signed, name-free datasets and attaches names locally', function () {
    $setup = analyticsSchool();
    fakeAnalytics();
    $user = userWithPermissions(['analytics.view', 'attendance.view'], $setup['school']);

    $this->actingAs($user)
        ->get(route('analytics.insights'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('analytics/insights')
            ->where('sections', ['attendance' => true, 'performance' => false, 'finance' => false])
            ->where('finance', null)
            ->missing('atRisk')
            ->loadDeferredProps('attendance', fn (Assert $reload) => $reload
                ->where('attendance.available', true)
                ->where('attendance.data.summary.overall_rate', 33.33)
                ->where('atRisk.data.students.0.name', "Ali {$setup['student']->last_name}")
                ->where('atRisk.data.students.0.admission_no', $setup['student']->admission_no)));

    Http::assertSent(function (HttpRequest $request) use ($setup): bool {
        if (! str_ends_with($request->url(), '/v1/attendance/at-risk')) {
            return false;
        }

        $expected = app(AnalyticsClient::class)->signatureHeaders('POST', '/v1/attendance/at-risk', $request->body(), (int) $request->header('X-Timestamp')[0]);

        return $request->header('X-Signature')[0] === $expected['X-Signature']
            && $request['students'][0]['absent'] === 2
            && $request['students'][0]['recent_absences'] === 2
            && ! str_contains($request->body(), 'Ali')
            && ! str_contains($request->body(), $setup['student']->last_name);
    });
    Http::assertNotSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'finance'));
});

test('datasets only include the current school', function () {
    $setup = analyticsSchool();
    $other = analyticsSchool();
    Invoice::factory()->create(['school_id' => $other['school']->id, 'student_id' => $other['student']->id, 'academic_year_id' => $other['year']->id, 'status' => 'unpaid', 'total' => 99900, 'paid' => 0]);
    fakeAnalytics();

    $this->actingAs(userWithPermissions(['analytics.view', 'accounting.view'], $setup['school']))
        ->get(route('analytics.insights'))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps('finance', fn (Assert $reload) => $reload->where('finance.available', true)));

    Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/v1/finance/forecast') && $request['open_invoices'] === []);
});

test('performance sends percentages from published exams only, oldest first', function () {
    $setup = analyticsSchool();
    $scale = app(GradeScaleService::class)->defaultFor($setup['school']->id);
    $examFor = function (string $name, string $startsOn, bool $published, float $obtained) use ($setup, $scale): void {
        $exam = Exam::factory()->create(['school_id' => $setup['school']->id, 'academic_year_id' => $setup['year']->id, 'grade_scale_id' => $scale->id, 'name' => $name, 'starts_on' => $startsOn, 'ends_on' => $startsOn, 'published_at' => $published ? now() : null]);
        $paper = ExamPaper::factory()->create(['school_id' => $setup['school']->id, 'exam_id' => $exam->id, 'school_class_id' => $setup['section']->school_class_id, 'subject_id' => $setup['subject']->id, 'max_marks' => 50, 'pass_marks' => 20]);
        Mark::query()->create(['school_id' => $setup['school']->id, 'exam_paper_id' => $paper->id, 'student_id' => $setup['student']->id, 'marks_obtained' => $obtained]);
    };
    $examFor('Mid-term', now()->subMonths(2)->toDateString(), true, 15);
    $examFor('Final', now()->subMonth()->toDateString(), true, 40);
    $examFor('Draft', now()->toDateString(), false, 50);
    Http::fake(['analytics.test/v1/performance/summary' => Http::response(['subjects' => [], 'distribution' => [], 'correlation' => null, 'movers' => ['improved' => [], 'declined' => []], 'exams' => []])]);

    $this->actingAs(userWithPermissions(['analytics.view', 'exams.view'], $setup['school']))
        ->get(route('analytics.insights'))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps('performance', fn (Assert $reload) => $reload->where('performance.available', true)));

    Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/v1/performance/summary')
        && array_column($request['exams'], 'exam') === ['Mid-term', 'Final']
        && $request['exams'][0]['scores'][0]['percentage'] === 30.0
        && $request['exams'][0]['scores'][0]['passed'] === false
        && $request['exams'][1]['scores'][0]['percentage'] === 80.0
        && $request['attendance'][(string) $setup['student']->id] === 33.33);
});

test('an unreachable service degrades to unavailable and is not cached', function () {
    $setup = analyticsSchool();
    $user = userWithPermissions(['analytics.view', 'attendance.view'], $setup['school']);
    $online = false;
    Http::fake(['analytics.test/*' => function () use (&$online) {
        return $online
            ? Http::response(['daily' => [], 'weekdays' => [], 'summary' => null, 'students' => []])
            : Http::response('down', 503);
    }]);

    $this->actingAs($user)->get(route('analytics.insights'))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps('attendance', fn (Assert $reload) => $reload
            ->where('attendance.available', false)
            ->where('attendance.data', null)));

    $online = true;

    $this->actingAs($user)->get(route('analytics.insights'))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps('attendance', fn (Assert $reload) => $reload->where('attendance.available', true)));
});

test('without a configured secret no request is made', function () {
    config(['services.analytics.secret' => null]);
    $setup = analyticsSchool();
    Http::fake();

    $this->actingAs(userWithPermissions(['analytics.view', 'attendance.view'], $setup['school']))
        ->get(route('analytics.insights'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('configured', false)
            ->loadDeferredProps('attendance', fn (Assert $reload) => $reload->where('attendance.available', false)));

    Http::assertNotSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'analytics.test'));
});

test('the interactive report downloads as an attachment and is audited', function () {
    $setup = analyticsSchool();
    fakeAnalytics();
    $user = userWithPermissions(['analytics.view', 'analytics.export', 'attendance.view'], $setup['school']);

    $this->actingAs($user)
        ->get(route('analytics.report'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertDownload();

    Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/v1/reports/html') && isset($request['attendance']) && ! isset($request['finance']));
    expect(AuditLog::query()->where('event', 'analytics.report_exported')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('the interactive report requires analytics.export', function () {
    $setup = analyticsSchool();

    $this->actingAs(userWithPermissions(['analytics.view', 'attendance.view'], $setup['school']))
        ->get(route('analytics.report'))
        ->assertForbidden();
});
