<?php

namespace App\Http\Controllers\Academics\OnlineExams;

use App\Models\OnlineExam;
use App\Services\Academics\Exams\OnlineExamReports;
use App\Services\Academics\Exams\OnlineExamScope;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\CsvExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exam reports (one exam) and the performance overview (exam-, class-, subject- and
 * student-wise), with downloads.
 */
class ExamReportController extends OnlineExamController
{
    public function __construct(
        OnlineExamScope $scope,
        private OnlineExamReports $reports,
        private AuditLogger $audit,
    ) {
        parent::__construct($scope);
    }

    public function show(Request $request, OnlineExam $onlineExam): Response
    {
        $this->authorizeView($onlineExam);

        return Inertia::render('academics/online-exams/report', [
            'exam' => $this->present($onlineExam),
            'report' => $this->reports->exam($onlineExam),
            'can' => ['export' => $request->user()->hasPermission('online_exams.export')],
        ]);
    }

    public function export(Request $request, OnlineExam $onlineExam, CsvExporter $csv): StreamedResponse|BinaryFileResponse
    {
        $this->authorizeView($onlineExam);
        abort_unless($request->user()->hasPermission('online_exams.export'), 403);
        $format = $request->validate(['format' => ['nullable', 'in:csv,xlsx']])['format'] ?? 'xlsx';

        [$headings, $rows] = $this->reports->exportRows($onlineExam);
        $name = Str::slug($onlineExam->title).'-results';
        $this->audit->log('online_exam.results_exported', $onlineExam, [], ['format' => $format, 'rows' => count($rows)]);

        if ($format === 'csv') {
            return $csv->download("{$name}.csv", $headings, $rows);
        }

        return response()->download($this->xlsx($onlineExam->title, $headings, $rows, $csv), "{$name}.xlsx")->deleteFileAfterSend();
    }

    public function overview(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->scope->isStaff($user), 403);
        $year = $this->currentYear();
        $filters = $this->filters($request);
        $options = $this->scope->teachingOptions($user, $year);

        return Inertia::render('academics/online-exams/reports', [
            'overview' => $this->reports->overview($user, $year, $filters),
            'filters' => $filters,
            'options' => $options,
            'can' => ['export' => $user->hasPermission('online_exams.export')],
        ]);
    }

    public function exportOverview(Request $request, CsvExporter $csv): StreamedResponse
    {
        $user = $request->user();
        abort_unless($this->scope->isStaff($user) && $user->hasPermission('online_exams.export'), 403);
        $type = $request->validate(['type' => ['required', 'in:exams,classes,subjects,students']])['type'];
        $overview = $this->reports->overview($user, $this->currentYear(), $this->filters($request));

        [$headings, $rows] = match ($type) {
            'exams' => [[__('Exam'), __('Class'), __('Subject'), __('Date'), __('Students'), __('Average %'), __('Pass rate %'), __('Highest %')],
                array_map(fn (array $row): array => [$row['title'], $row['class'], $row['subject'], substr($row['date'], 0, 10), $row['attempts'], $row['average'], $row['pass_rate'], $row['highest']], $overview['exams'])],
            'students' => [[__('Rank'), __('Admission no.'), __('Student'), __('Exams'), __('Average %'), __('Passed'), __('Best %'), __('Lowest %')],
                array_map(fn (array $row): array => [$row['rank'], $row['admission_no'], $row['name'], $row['exams'], $row['average'], $row['passed'], $row['best'], $row['lowest']], $overview['students'])],
            default => [[$type === 'classes' ? __('Class') : __('Subject'), __('Results'), __('Average %'), __('Pass rate %'), __('Highest %')],
                array_map(fn (array $row): array => [$row['name'], $row['attempts'], $row['average'], $row['pass_rate'], $row['highest']], $overview[$type])],
        };

        $this->audit->log('online_exam.report_exported', null, [], ['type' => $type, 'rows' => count($rows)]);

        return $csv->download("online-exams-{$type}-".now()->toDateString().'.csv', $headings, $rows);
    }

    /**
     * @return array{class_id: int|null, subject_id: int|null, section_id: int|null}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'class_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
        ]);

        return [
            'class_id' => isset($validated['class_id']) ? (int) $validated['class_id'] : null,
            'subject_id' => isset($validated['subject_id']) ? (int) $validated['subject_id'] : null,
            'section_id' => isset($validated['section_id']) ? (int) $validated['section_id'] : null,
        ];
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $rows
     */
    private function xlsx(string $title, array $headings, array $rows, CsvExporter $csv): string
    {
        $path = tempnam(sys_get_temp_dir(), 'exam').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(Str::limit(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $title) ?? 'Results', 28, ''));
        $writer->getCurrentSheet()->setColumnWidthForRange(16, 1, max(1, count($headings)));
        $writer->addRow(Row::fromValuesWithStyle($headings, (new Style)->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor('1E3A8A')));

        foreach ($rows as $row) {
            // Text cells get the same formula-injection guard as the CSV export.
            $writer->addRow(Row::fromValues(array_map(fn (mixed $cell): mixed => is_string($cell) ? $csv->cell($cell) : $cell, $row)));
        }

        $writer->close();

        return $path;
    }
}
