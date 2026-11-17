<?php

namespace Database\Seeders;

use App\Enums\Audience;
use App\Enums\ExpenseStatus;
use App\Enums\FeeFrequency;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementType;
use App\Enums\SubjectType;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Exam;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeItem;
use App\Models\InventoryItem;
use App\Models\Period;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolEvent;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TransportRoute;
use App\Models\User;
use App\Services\Academics\ExamService;
use App\Services\Academics\GradeScaleService;
use App\Services\Academics\PeriodService;
use App\Services\Communication\AnnouncementService;
use App\Services\Finance\ExpenseService;
use App\Services\Finance\LedgerService;
use App\Services\Hr\LeaveService;
use App\Services\Inventory\InventoryService;
use App\Services\Tenancy\CurrentSchool;
use App\Services\Transport\TransportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Everything a demo school needs except people and books, which are loaded from the
 * Excel files made by `php artisan demo:spreadsheets`. Safe to run more than once.
 */
class DemoSchoolDataSeeder extends Seeder
{
    private School $school;

    private User $admin;

    private User $approver;

    public function run(CurrentSchool $currentSchool): void
    {
        $this->school = School::query()->where('code', 'GHS')->firstOrFail();
        $this->admin = User::query()->where('email', 'school.admin@example.com')->firstOrFail();
        $this->approver = User::query()->where('email', 'principal@example.com')->firstOrFail();

        $currentSchool->set($this->school);

        $year = $this->academicYears();
        $subjects = $this->subjects();
        $this->classes($subjects);
        $this->bellSchedule();
        $scale = app(GradeScaleService::class)->defaultFor($this->school->id);
        $this->exams($year, $scale->id);
        $this->finance($year);
        app(LeaveService::class)->ensureTypes($this->school->id);
        $this->transport();
        $this->inventory();
        $this->communication();
    }

    private function academicYears(): AcademicYear
    {
        AcademicYear::query()->updateOrCreate(
            ['school_id' => $this->school->id, 'name' => '2025-26'],
            ['starts_on' => '2025-04-01', 'ends_on' => '2026-03-31', 'is_current' => false],
        );

        $year = AcademicYear::query()->updateOrCreate(
            ['school_id' => $this->school->id, 'name' => '2026-27'],
            ['starts_on' => '2026-04-01', 'ends_on' => '2027-03-31', 'is_current' => true],
        );

        foreach ([['Term 1', '2026-04-01', '2026-09-30'], ['Term 2', '2026-10-01', '2027-03-31']] as [$name, $from, $to]) {
            Term::query()->updateOrCreate(
                ['academic_year_id' => $year->id, 'name' => $name],
                ['school_id' => $this->school->id, 'starts_on' => $from, 'ends_on' => $to],
            );
        }

        return $year;
    }

    /**
     * @return array<string, Subject>
     */
    private function subjects(): array
    {
        $definitions = [
            ['English', 'ENG', SubjectType::Core, '#1d4ed8'],
            ['Mathematics', 'MATH', SubjectType::Core, '#4338ca'],
            ['Science', 'SCI', SubjectType::Core, '#059669'],
            ['Social Studies', 'SST', SubjectType::Core, '#b45309'],
            ['Computer Science', 'CS', SubjectType::Elective, '#0891b2'],
            ['Art & Design', 'ART', SubjectType::Elective, '#db2777'],
            ['Physical Education', 'PE', SubjectType::Extracurricular, '#ea580c'],
            ['Physics', 'PHY', SubjectType::Core, '#7c3aed'],
            ['Chemistry', 'CHEM', SubjectType::Core, '#0d9488'],
            ['Biology', 'BIO', SubjectType::Core, '#16a34a'],
        ];

        $subjects = [];

        foreach ($definitions as [$name, $code, $type, $color]) {
            $subjects[$code] = Subject::query()->updateOrCreate(
                ['school_id' => $this->school->id, 'code' => $code],
                ['name' => $name, 'type' => $type, 'color' => $color, 'is_active' => true],
            );
        }

        return $subjects;
    }

    /**
     * Class 1–10, each with sections A and B. Juniors study general science; classes 9–10
     * split it into physics, chemistry and biology.
     *
     * @param  array<string, Subject>  $subjects
     */
    private function classes(array $subjects): void
    {
        foreach (range(1, 10) as $level) {
            $class = SchoolClass::query()->updateOrCreate(
                ['school_id' => $this->school->id, 'name' => "Class {$level}"],
                ['level' => $level, 'description' => $level <= 5 ? 'Primary school' : ($level <= 8 ? 'Middle school' : 'Secondary school')],
            );

            foreach (['A', 'B'] as $index => $name) {
                Section::query()->updateOrCreate(
                    ['school_class_id' => $class->id, 'name' => $name],
                    ['school_id' => $this->school->id, 'capacity' => 30, 'room' => sprintf('R-%d%02d', $index + 1, $level)],
                );
            }

            $codes = $level <= 8
                ? ['ENG' => 6, 'MATH' => 6, 'SCI' => 5, 'SST' => 4, 'CS' => 2, 'ART' => 2, 'PE' => 2]
                : ['ENG' => 5, 'MATH' => 6, 'PHY' => 4, 'CHEM' => 4, 'BIO' => 4, 'CS' => 3, 'PE' => 1];

            $class->subjects()->sync(collect($codes)->mapWithKeys(fn (int $periods, string $code): array => [
                $subjects[$code]->id => ['periods_per_week' => $periods],
            ])->all());
        }
    }

    private function bellSchedule(): void
    {
        if (Period::query()->where('school_id', $this->school->id)->exists()) {
            return;
        }

        app(PeriodService::class)->sync($this->school->id, [
            ['id' => null, 'name' => 'Period 1', 'starts_at' => '08:00', 'ends_at' => '08:45', 'is_break' => false],
            ['id' => null, 'name' => 'Period 2', 'starts_at' => '08:45', 'ends_at' => '09:30', 'is_break' => false],
            ['id' => null, 'name' => 'Period 3', 'starts_at' => '09:30', 'ends_at' => '10:15', 'is_break' => false],
            ['id' => null, 'name' => 'Break', 'starts_at' => '10:15', 'ends_at' => '10:35', 'is_break' => true],
            ['id' => null, 'name' => 'Period 4', 'starts_at' => '10:35', 'ends_at' => '11:20', 'is_break' => false],
            ['id' => null, 'name' => 'Period 5', 'starts_at' => '11:20', 'ends_at' => '12:05', 'is_break' => false],
            ['id' => null, 'name' => 'Lunch', 'starts_at' => '12:05', 'ends_at' => '12:45', 'is_break' => true],
            ['id' => null, 'name' => 'Period 6', 'starts_at' => '12:45', 'ends_at' => '13:30', 'is_break' => false],
            ['id' => null, 'name' => 'Period 7', 'starts_at' => '13:30', 'ends_at' => '14:15', 'is_break' => false],
        ]);
    }

    private function exams(AcademicYear $year, int $gradeScaleId): void
    {
        $terms = Term::query()->where('academic_year_id', $year->id)->pluck('id', 'name');
        $exams = app(ExamService::class);

        foreach ([
            ['Mid-term examination', 'Term 1', '2026-09-14', '2026-09-25'],
            ['Final examination', 'Term 2', '2027-03-08', '2027-03-19'],
        ] as [$name, $term, $from, $to]) {
            $exam = Exam::query()->firstOrNew(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => $name]);

            if (! $exam->exists) {
                $exam = $exams->save($exam, [
                    'academic_year_id' => $year->id,
                    'term_id' => $terms[$term] ?? null,
                    'grade_scale_id' => $gradeScaleId,
                    'name' => $name,
                    'starts_on' => $from,
                    'ends_on' => $to,
                ]);
            }

            $exams->generatePapers($exam);
        }
    }

    private function finance(AcademicYear $year): void
    {
        app(LedgerService::class)->ensureAccounts($this->school->id);
        $expenses = app(ExpenseService::class);
        $expenses->ensureCategories($this->school->id);

        $classes = SchoolClass::query()->pluck('id', 'level');

        foreach ($classes as $level => $classId) {
            $tuition = match (true) {
                $level <= 5 => 18000,
                $level <= 8 => 22000,
                default => 26000,
            };

            FeeItem::query()->updateOrCreate(
                ['academic_year_id' => $year->id, 'school_class_id' => $classId, 'name' => 'Tuition fee'],
                ['school_id' => $this->school->id, 'amount' => $tuition, 'frequency' => FeeFrequency::Monthly, 'is_active' => true],
            );
        }

        foreach ([
            ['Admission fee', 25000, FeeFrequency::Once],
            ['Examination fee', 4000, FeeFrequency::Termly],
            ['Library & lab fee', 6000, FeeFrequency::Annual],
            ['Sports & activities', 3500, FeeFrequency::Termly],
        ] as [$name, $amount, $frequency]) {
            FeeItem::query()->updateOrCreate(
                ['academic_year_id' => $year->id, 'school_class_id' => null, 'name' => $name],
                ['school_id' => $this->school->id, 'amount' => $amount, 'frequency' => $frequency, 'is_active' => true],
            );
        }

        if (Expense::query()->where('school_id', $this->school->id)->exists()) {
            return;
        }

        $categories = ExpenseCategory::query()->pluck('id', 'name');
        $category = fn (string $wanted): int => (int) ($categories[$wanted] ?? $categories->first());

        foreach ([
            ['Utilities', 'City Power & Light', 'Electricity, October', 84250, '2026-10-28', PaymentMethod::BankTransfer, true],
            ['Utilities', 'Springfield Water', 'Water and sewerage, October', 21300, '2026-10-29', PaymentMethod::BankTransfer, true],
            ['Maintenance', 'Brightline Repairs', 'Classroom projector repairs', 46500, '2026-10-12', PaymentMethod::Cheque, true],
            ['Supplies & stationery', 'Office Depot', 'Printer paper and toner', 31890, '2026-11-03', PaymentMethod::Card, true],
            ['Supplies & stationery', 'Lab Supply Co.', 'Chemistry lab consumables', 58200, '2026-11-06', PaymentMethod::BankTransfer, true],
            ['Events', 'Greenfield Caterers', 'Sports day refreshments', 39000, '2026-11-10', PaymentMethod::Cash, false],
        ] as [$categoryName, $payee, $description, $amount, $spentOn, $method, $approve]) {
            $expense = $expenses->submit($this->admin, $this->school->id, [
                'expense_category_id' => $category($categoryName),
                'payee' => $payee,
                'description' => $description,
                'amount' => $amount,
                'spent_on' => $spentOn,
                'method' => $method,
                'reference' => null,
            ]);

            if ($approve) {
                $expenses->review($this->approver, $expense, ExpenseStatus::Approved, 'Checked against the invoice.');
            }
        }
    }

    private function transport(): void
    {
        $transport = app(TransportService::class);

        foreach ([
            ['North loop', 'GHS-BUS-01', 'Daniel Brooks', '+1 555 0141', 40, [['Maple Avenue', '07:05', '14:55'], ['Oak Park', '07:15', '14:45'], ['Riverside Mall', '07:25', '14:35']]],
            ['East loop', 'GHS-BUS-02', 'Maria Lopez', '+1 555 0142', 40, [['Cedar Heights', '07:00', '15:00'], ['Station Road', '07:12', '14:48'], ['Hillview', '07:24', '14:36']]],
            ['South loop', 'GHS-BUS-03', 'Kwame Mensah', '+1 555 0143', 30, [['Lakeside', '07:08', '14:52'], ['Pine Street', '07:18', '14:42']]],
            ['West loop', 'GHS-VAN-01', 'Sofia Rossi', '+1 555 0144', 14, [['Westgate', '07:10', '14:50'], ['Elm Court', '07:20', '14:40']]],
        ] as [$name, $vehicle, $driver, $phone, $capacity, $stops]) {
            $route = TransportRoute::query()->updateOrCreate(
                ['school_id' => $this->school->id, 'name' => $name],
                ['vehicle_no' => $vehicle, 'driver_name' => $driver, 'driver_phone' => $phone, 'capacity' => $capacity, 'is_active' => true],
            );

            if (! $route->stops()->exists()) {
                $transport->syncStops($route, array_map(fn (array $stop): array => [
                    'id' => null,
                    'name' => $stop[0],
                    'pickup_time' => $stop[1],
                    'drop_time' => $stop[2],
                ], $stops));
            }
        }
    }

    private function inventory(): void
    {
        $inventory = app(InventoryService::class);

        foreach ([
            ['Whiteboard markers (box of 12)', 'STN-001', 'Stationery', 'box', 'Store room A', 10, 48],
            ['A4 printer paper (ream)', 'STN-002', 'Stationery', 'ream', 'Store room A', 20, 120],
            ['Chalk (box)', 'STN-003', 'Stationery', 'box', 'Store room A', 15, 35],
            ['Exercise books', 'STN-004', 'Stationery', 'piece', 'Store room A', 200, 650],
            ['Projector', 'ICT-001', 'ICT', 'piece', 'ICT office', 2, 14],
            ['Laptop', 'ICT-002', 'ICT', 'piece', 'Computer lab', 5, 32],
            ['HDMI cable', 'ICT-003', 'ICT', 'piece', 'ICT office', 10, 25],
            ['Microscope', 'LAB-001', 'Laboratory', 'piece', 'Biology lab', 4, 18],
            ['Test tubes (pack of 50)', 'LAB-002', 'Laboratory', 'pack', 'Chemistry lab', 5, 22],
            ['Safety goggles', 'LAB-003', 'Laboratory', 'pair', 'Chemistry lab', 20, 60],
            ['Football', 'SPT-001', 'Sports', 'piece', 'Sports room', 5, 16],
            ['Basketball', 'SPT-002', 'Sports', 'piece', 'Sports room', 5, 12],
            ['First-aid kit', 'MED-001', 'Health', 'kit', 'Nurse office', 3, 9],
            ['Hand sanitiser (litre)', 'MED-002', 'Health', 'bottle', 'Nurse office', 10, 30],
            ['Student desk', 'FUR-001', 'Furniture', 'piece', 'Warehouse', 10, 340],
        ] as [$name, $sku, $category, $unit, $location, $reorder, $stock]) {
            $item = InventoryItem::query()->updateOrCreate(
                ['school_id' => $this->school->id, 'sku' => $sku],
                ['name' => $name, 'category' => $category, 'unit' => $unit, 'location' => $location, 'reorder_level' => $reorder],
            );

            if ($item->movements()->doesntExist()) {
                $inventory->record($item, StockMovementType::In, $stock, 'Opening stock', $this->admin);
                $inventory->record($item, StockMovementType::Out, (int) max(1, round($stock * 0.15)), 'Issued to classrooms', $this->admin);
            }
        }
    }

    private function communication(): void
    {
        $announcements = app(AnnouncementService::class);

        foreach ([
            ['Welcome to the 2026-27 academic year', 'We are delighted to welcome all students and families to a new year at Greenfield. Timetables are now available in the portal.', Audience::Everyone, true],
            ['Mid-term results published', 'Mid-term examination results and report cards can now be viewed in the portal.', Audience::Everyone, false],
            ['Staff development day', 'Friday 27 November is a staff development day. There are no classes for students.', Audience::Staff, true],
            ['Winter uniform reminder', 'From 1 December students should wear the winter uniform, including the school blazer.', Audience::Guardians, false],
            ['Science fair registrations open', 'Students in classes 6–10 can register their science fair projects with their science teacher by 4 December.', Audience::Students, false],
        ] as [$title, $body, $audience, $pinned]) {
            $announcement = Announcement::query()->firstOrCreate(
                ['school_id' => $this->school->id, 'title' => $title],
                ['body' => $body, 'audience' => $audience, 'is_pinned' => $pinned, 'created_by' => $this->admin->id],
            );

            if ($announcement->published_at === null) {
                $announcements->publish($announcement, $this->approver);
            }
        }

        $base = CarbonImmutable::parse('2026-11-01');

        foreach ([
            ['Parent–teacher meetings', 'Meet your child’s class teacher to discuss progress.', 'School hall', 19, '15:00', '18:00', Audience::Guardians],
            ['Staff development day', 'Training on assessment and the new reporting tools.', 'Library', 27, '09:00', '15:00', Audience::Staff],
            ['Annual sports day', 'Track and field events for all classes. Families welcome.', 'Main field', 33, '08:30', '14:00', Audience::Everyone],
            ['Science fair', 'Projects from classes 6–10 on display.', 'Science block', 40, '10:00', '13:00', Audience::Everyone],
            ['Winter concert', 'Music and drama performances by our students.', 'Auditorium', 46, '17:00', '19:30', Audience::Everyone],
            ['Winter break begins', 'School closes for the winter break.', null, 52, null, null, Audience::Everyone],
        ] as [$title, $description, $location, $dayOffset, $from, $to, $audience]) {
            $day = $base->addDays($dayOffset);

            SchoolEvent::query()->firstOrCreate(
                ['school_id' => $this->school->id, 'title' => $title],
                [
                    'description' => $description,
                    'location' => $location,
                    'starts_at' => $from ? $day->setTimeFromTimeString($from) : $day->startOfDay(),
                    'ends_at' => $to ? $day->setTimeFromTimeString($to) : $day->endOfDay(),
                    'all_day' => $from === null,
                    'audience' => $audience,
                    'created_by' => $this->admin->id,
                ],
            );
        }
    }
}
