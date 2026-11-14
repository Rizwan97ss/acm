<?php

namespace App\Services\Analytics;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Services\Settings\SettingsService;
use App\Services\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Combines Laravel-built datasets with the analytics service's results for the Insights
 * page. Successful results are cached per school; failures are never cached so the page
 * recovers as soon as the service is back.
 *
 * Each method returns ['available' => bool, 'data' => array|null].
 */
class InsightsService
{
    public function __construct(
        private AnalyticsClient $client,
        private AnalyticsDatasets $datasets,
        private CurrentSchool $currentSchool,
        private SettingsService $settings,
    ) {}

    /**
     * @return array{available: bool, data: array<string, mixed>|null}
     */
    public function attendanceTrends(): array
    {
        return $this->remember('attendance-trends', fn (): ?array => $this->client->compute(
            '/v1/attendance/trends',
            $this->datasets->attendanceDays(CarbonImmutable::today()->subDays(119), CarbonImmutable::today()),
        ));
    }

    /**
     * @return array{available: bool, data: array<string, mixed>|null}
     */
    public function atRisk(): array
    {
        $year = AcademicYear::query()->current()->first();

        if ($year === null) {
            return ['available' => true, 'data' => null];
        }

        $threshold = (float) $this->settings->current('academic.attendance_alert_threshold');

        $result = $this->remember("at-risk:{$year->id}:{$threshold}", fn (): ?array => $this->client->compute(
            '/v1/attendance/at-risk',
            $this->datasets->studentAttendance($year, $threshold),
        ));

        if ($result['data'] !== null) {
            $result['data']['students'] = $this->withStudentNames(array_slice($result['data']['students'], 0, 25));
        }

        return $result;
    }

    /**
     * @return array{available: bool, data: array<string, mixed>|null}
     */
    public function performance(): array
    {
        $year = AcademicYear::query()->current()->first();

        if ($year === null) {
            return ['available' => true, 'data' => null];
        }

        $result = $this->remember("performance:{$year->id}", fn (): ?array => $this->client->compute(
            '/v1/performance/summary',
            $this->datasets->performance($year),
        ));

        if ($result['data'] !== null) {
            $result['data']['movers']['improved'] = $this->withStudentNames($result['data']['movers']['improved']);
            $result['data']['movers']['declined'] = $this->withStudentNames($result['data']['movers']['declined']);
        }

        return $result;
    }

    /**
     * @return array{available: bool, data: array<string, mixed>|null}
     */
    public function finance(): array
    {
        return $this->remember('finance', fn (): ?array => $this->client->compute('/v1/finance/forecast', $this->datasets->finance()));
    }

    /**
     * A downloadable interactive HTML report, or null when the service is unavailable.
     *
     * @param  array{attendance: bool, finance: bool}  $sections
     */
    public function htmlReport(string $title, array $sections): ?string
    {
        return $this->client->report(array_filter([
            'title' => $title,
            'attendance' => $sections['attendance'] ? $this->datasets->attendanceDays(CarbonImmutable::today()->subDays(119), CarbonImmutable::today()) : null,
            'finance' => $sections['finance'] ? $this->datasets->finance() : null,
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * @param  Closure(): (array<string, mixed>|null)  $compute
     * @return array{available: bool, data: array<string, mixed>|null}
     */
    private function remember(string $name, Closure $compute): array
    {
        $key = sprintf('analytics:%d:%s:%s', (int) $this->currentSchool->id(), $name, now()->toDateString());
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return ['available' => true, 'data' => $cached];
        }

        $data = $compute();

        if ($data === null) {
            return ['available' => false, 'data' => null];
        }

        Cache::put($key, $data, now()->addMinutes((int) config('services.analytics.cache_minutes', 10)));

        return ['available' => true, 'data' => $data];
    }

    /**
     * Attaches names and admission numbers, which never leave Laravel. Rows come from
     * the analytics service, so anything malformed is dropped.
     *
     * @param  array<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function withStudentNames(array $rows): array
    {
        $students = Student::query()
            ->whereIn('id', array_column(array_filter($rows, is_array(...)), 'student_id'))
            ->get(['id', 'first_name', 'last_name', 'admission_no'])
            ->keyBy('id');

        return array_values(array_filter(array_map(function (mixed $row) use ($students): ?array {
            $student = is_array($row) && is_int($row['student_id'] ?? null) ? $students->get($row['student_id']) : null;

            return $student === null ? null : [...$row, 'name' => "{$student->first_name} {$student->last_name}", 'admission_no' => $student->admission_no];
        }, $rows)));
    }
}
