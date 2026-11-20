<?php

namespace Database\Seeders;

use App\Enums\AssignmentType;
use App\Enums\AttendanceStatus;
use App\Enums\Audience;
use App\Enums\CertificateType;
use App\Enums\LeaveStatus;
use App\Enums\LiveClassStatus;
use App\Enums\MaterialKind;
use App\Enums\MeetingProvider;
use App\Enums\PaymentMethod;
use App\Enums\PayrollStatus;
use App\Enums\PublishStatus;
use App\Enums\QuizType;
use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\Certificate;
use App\Models\ContactInquiry;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\FeeConcession;
use App\Models\FeeItem;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\LessonProgress;
use App\Models\LibraryBook;
use App\Models\LibraryLoan;
use App\Models\LiveClass;
use App\Models\PayrollRun;
use App\Models\Period;
use App\Models\Quiz;
use App\Models\Role;
use App\Models\SalaryProfile;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\TimetableEntry;
use App\Models\TransportAssignment;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Services\Academics\AssignmentService;
use App\Services\Academics\ExamService;
use App\Services\Communication\MessagingService;
use App\Services\Finance\BillingService;
use App\Services\Hr\LeaveService;
use App\Services\Hr\PayrollService;
use App\Services\Learning\QuizService;
use App\Services\Library\LibraryService;
use App\Services\Rbac\PermissionResolver;
use App\Services\Records\CertificateService;
use App\Services\Tenancy\CurrentSchool;
use App\Services\Transport\TransportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Day-to-day activity for the demo school, built on the students, parents, staff and books
 * imported from the demo spreadsheets. Each part skips itself when its data already
 * exists, so it can be re-run (for example after importing the library books).
 *
 * Every new login uses the password "password".
 */
class DemoActivitySeeder extends Seeder
{
    private School $school;

    private AcademicYear $year;

    private User $admin;

    private User $principal;

    private User $accountant;

    private User $hr;

    private User $librarian;

    private CarbonImmutable $today;

    /**
     * Subject code => teacher user ids able to teach it.
     *
     * @var array<string, list<int>>
     */
    private array $teachersBySubject = [];

    private const array DESIGNATION_SUBJECTS = [
        'English teacher' => ['ENG'],
        'Mathematics teacher' => ['MATH'],
        'Science teacher' => ['SCI'],
        'Social studies teacher' => ['SST'],
        'Computer science teacher' => ['CS'],
        'Art teacher' => ['ART'],
        'Physical education teacher' => ['PE'],
        'Physics teacher' => ['PHY'],
        'Chemistry teacher' => ['CHEM'],
        'Biology teacher' => ['BIO'],
        'Primary class teacher' => ['ENG', 'MATH', 'SST'],
    ];

    private const array DESIGNATION_ROLES = [
        'Vice principal' => 'principal',
        'Accountant' => 'accountant',
        'Assistant accountant' => 'accountant',
        'Librarian' => 'librarian',
        'Receptionist' => 'receptionist',
        'Admissions officer' => 'receptionist',
    ];

    public function run(CurrentSchool $currentSchool): void
    {
        $this->school = School::query()->where('code', 'GHS')->firstOrFail();
        $currentSchool->set($this->school);

        $this->year = AcademicYear::query()->current()->firstOrFail();
        $this->today = CarbonImmutable::today();

        $user = fn (string $email): User => User::query()->where('email', $email)->firstOrFail();
        $this->admin = $user('school.admin@example.com');
        $this->principal = $user('principal@example.com');
        $this->accountant = $user('accountant@example.com');
        $this->hr = $user('hr@example.com');
        $this->librarian = $user('librarian@example.com');

        if (Enrollment::query()->where('academic_year_id', $this->year->id)->doesntExist()) {
            $this->command?->warn('No enrolled students yet. Import 1-students-and-parents.xlsx first, then run this seeder again.');

            return;
        }

        mt_srand(7);

        $this->step('Staff login accounts', fn () => $this->staffAccounts());
        $this->step('Class teachers and timetable', fn () => $this->timetable());
        $this->step('Attendance', fn () => $this->attendance());
        $this->step('Mid-term marks', fn () => $this->marks());
        $this->step('Assignments', fn () => $this->assignments());
        $this->step('Fee concessions, invoices and payments', fn () => $this->fees());
        $this->step('Salary profiles, leave and payroll', fn () => $this->hrActivity());
        $this->step('Transport seats', fn () => $this->transport());
        $this->call(DemoHostelSeeder::class);
        $this->call(DemoOnlineExamSeeder::class);
        $this->step('Portal accounts and messages', fn () => $this->portalAndMessages());
        $this->step('Documents and certificates', fn () => $this->records());
        $this->step('Library loans', fn () => $this->library());
        $this->step('Courses, live classes and quizzes', fn () => $this->learning());
        $this->step('Website enquiries and webhook', fn () => $this->administration());

        app(PermissionResolver::class)->flush();
    }

    private function step(string $label, callable $callback): void
    {
        $this->command?->getOutput()->write("  {$label} … ");
        $result = $callback();
        $this->command?->getOutput()->writeln(is_string($result) ? $result : 'done');
    }

    /* ---------------------------------------------------------------- staff */

    private function staffAccounts(): string
    {
        $roles = Role::query()->whereNull('school_id')->pluck('id', 'slug');
        $created = 0;

        foreach (Employee::query()->whereNull('user_id')->get() as $employee) {
            $role = isset(self::DESIGNATION_SUBJECTS[$employee->designation]) ? 'teacher' : (self::DESIGNATION_ROLES[$employee->designation] ?? null);

            if ($role === null || $employee->email === null || User::query()->where('email', $employee->email)->exists()) {
                continue;
            }

            $account = User::query()->create([
                'name' => $employee->full_name,
                'email' => $employee->email,
                'password' => 'password',
                'school_id' => $this->school->id,
            ]);
            $account->forceFill(['email_verified_at' => now()])->save();
            $account->roles()->syncWithoutDetaching([$roles[$role] => ['created_at' => now()]]);
            $employee->update(['user_id' => $account->id]);
            $created++;
        }

        return "{$created} accounts";
    }

    private function loadTeachers(): void
    {
        $this->teachersBySubject = [];

        foreach (Employee::query()->whereNotNull('user_id')->where('is_teaching', true)->get() as $employee) {
            foreach (self::DESIGNATION_SUBJECTS[$employee->designation] ?? [] as $code) {
                $this->teachersBySubject[$code][] = (int) $employee->user_id;
            }
        }
    }

    /* -------------------------------------------------------------- academics */

    private function timetable(): string
    {
        if (TimetableEntry::query()->where('academic_year_id', $this->year->id)->exists()) {
            return 'already there';
        }

        $this->loadTeachers();
        $periods = Period::query()->where('is_break', false)->orderBy('position')->get();
        $sections = Section::query()->with('schoolClass.subjects')->get()->sortBy(fn (Section $section) => [$section->schoolClass->level, $section->name])->values();
        $busy = [];
        $load = [];
        $classTeacherCandidates = collect($this->teachersBySubject)->flatten()->unique()->values();
        $placed = 0;

        foreach ($sections as $index => $section) {
            $section->update(['class_teacher_id' => $classTeacherCandidates[$index % $classTeacherCandidates->count()]]);

            $teacherFor = [];
            $remaining = [];
            $periodsPerWeek = DB::table('class_subject')->where('school_class_id', $section->school_class_id)->pluck('periods_per_week', 'subject_id');

            foreach ($section->schoolClass->subjects as $subject) {
                $pool = $this->teachersBySubject[$subject->code] ?? [];

                if ($pool === []) {
                    continue;
                }

                usort($pool, fn (int $a, int $b) => ($load[$a] ?? 0) <=> ($load[$b] ?? 0));
                $teacherFor[$subject->id] = $pool[0];
                $load[$pool[0]] = ($load[$pool[0]] ?? 0) + (int) ($periodsPerWeek[$subject->id] ?? 0);
                $remaining[$subject->id] = (int) ($periodsPerWeek[$subject->id] ?? 0);
            }

            foreach (range(1, 5) as $day) {
                $todayCount = [];

                foreach ($periods as $period) {
                    $candidates = collect($remaining)->filter()->sortDesc()->keys();

                    foreach ($candidates as $subjectId) {
                        $teacher = $teacherFor[$subjectId];

                        if (isset($busy["{$teacher}|{$day}|{$period->id}"]) || ($todayCount[$subjectId] ?? 0) >= 2) {
                            continue;
                        }

                        TimetableEntry::query()->create([
                            'school_id' => $this->school->id,
                            'academic_year_id' => $this->year->id,
                            'section_id' => $section->id,
                            'day_of_week' => $day,
                            'period_id' => $period->id,
                            'subject_id' => $subjectId,
                            'teacher_id' => $teacher,
                            'room' => $section->room,
                        ]);

                        $busy["{$teacher}|{$day}|{$period->id}"] = true;
                        $todayCount[$subjectId] = ($todayCount[$subjectId] ?? 0) + 1;
                        $remaining[$subjectId]--;
                        $placed++;

                        break;
                    }
                }
            }
        }

        return "{$placed} lessons";
    }

    private function attendance(): string
    {
        if (AttendanceRecord::query()->where('academic_year_id', $this->year->id)->exists()) {
            return 'already there';
        }

        $enrollments = Enrollment::query()->where('academic_year_id', $this->year->id)->with('section')->get();
        $frequentAbsentees = $enrollments->pluck('student_id')->shuffle()->take(6)->flip();
        $rows = [];
        $now = now();

        for ($date = CarbonImmutable::parse('2026-09-01'); $date->lt($this->today); $date = $date->addDay()) {
            if ($date->isWeekend()) {
                continue;
            }

            foreach ($enrollments as $enrollment) {
                $roll = mt_rand(1, 100);
                $absentChance = isset($frequentAbsentees[$enrollment->student_id]) ? 20 : 4;

                $status = match (true) {
                    $roll <= $absentChance => AttendanceStatus::Absent,
                    $roll <= $absentChance + 4 => AttendanceStatus::Late,
                    $roll <= $absentChance + 5 => AttendanceStatus::Excused,
                    default => AttendanceStatus::Present,
                };

                $rows[] = [
                    'school_id' => $this->school->id,
                    'academic_year_id' => $this->year->id,
                    'section_id' => $enrollment->section_id,
                    'student_id' => $enrollment->student_id,
                    'date' => $date->toDateString(),
                    'status' => $status->value,
                    'remarks' => $status === AttendanceStatus::Excused ? 'Medical appointment' : null,
                    'marked_by' => $enrollment->section->class_teacher_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            AttendanceRecord::query()->insert($chunk);
        }

        return count($rows).' records';
    }

    private function marks(): string
    {
        $exam = Exam::query()->where('academic_year_id', $this->year->id)->where('name', 'Mid-term examination')->first();

        if ($exam === null || $exam->published_at !== null) {
            return 'already there';
        }

        $exams = app(ExamService::class);
        $ability = Enrollment::query()->where('academic_year_id', $this->year->id)->pluck('student_id')->mapWithKeys(fn (int $id) => [$id => mt_rand(48, 92)]);
        $sections = Section::query()->get()->groupBy('school_class_id');
        $papers = 0;

        foreach ($exam->papers()->get() as $index => $paper) {
            $paper->update(['held_on' => CarbonImmutable::parse($exam->starts_on)->addWeekdays($index % 9)->toDateString()]);

            foreach ($sections[$paper->school_class_id] ?? [] as $section) {
                $students = Enrollment::query()->where('academic_year_id', $this->year->id)->where('section_id', $section->id)->pluck('student_id');

                $rows = $students->mapWithKeys(function (int $studentId) use ($ability): array {
                    $absent = mt_rand(1, 60) === 1;

                    return [$studentId => [
                        'marks_obtained' => $absent ? null : (float) max(12, min(100, $ability[$studentId] + mt_rand(-15, 12))),
                        'is_absent' => $absent,
                        'remarks' => null,
                    ]];
                })->all();

                if ($rows !== []) {
                    $exams->recordMarks($this->admin, $exam, $paper, $section, $rows);
                    $papers++;
                }
            }
        }

        $exams->publish($exam, $this->principal);

        return "{$papers} mark sheets, published";
    }

    private function assignments(): string
    {
        if (Assignment::query()->where('academic_year_id', $this->year->id)->exists()) {
            return 'already there';
        }

        $service = app(AssignmentService::class);
        $timetable = TimetableEntry::query()->where('academic_year_id', $this->year->id)->get()->groupBy('section_id');
        $topics = ['Chapter review questions', 'Reading response', 'Problem set', 'Research project', 'Lab report', 'Map work', 'Creative writing'];
        $count = 0;

        foreach (Section::query()->get() as $section) {
            $lessons = ($timetable[$section->id] ?? collect())->unique('subject_id')->shuffle()->take(2);

            foreach ($lessons as $offset => $lesson) {
                $graded = $offset === 0;
                $assignment = Assignment::query()->create([
                    'school_id' => $this->school->id,
                    'academic_year_id' => $this->year->id,
                    'section_id' => $section->id,
                    'subject_id' => $lesson->subject_id,
                    'created_by' => $lesson->teacher_id,
                    'type' => $graded ? AssignmentType::Homework : AssignmentType::Project,
                    'title' => $topics[array_rand($topics)],
                    'description' => 'Complete the task described in class and submit your answer here.',
                    'due_on' => $graded ? $this->today->subDays(mt_rand(5, 20))->toDateString() : $this->today->addDays(mt_rand(4, 14))->toDateString(),
                    'max_marks' => $graded ? 20 : 50,
                ]);
                $count++;

                if (! $graded) {
                    continue;
                }

                $teacher = User::query()->whereKey($lesson->teacher_id)->first();

                foreach (Student::query()->whereIn('id', Enrollment::query()->where('section_id', $section->id)->select('student_id'))->get() as $student) {
                    if (mt_rand(1, 10) > 8) {
                        continue;
                    }

                    $submission = $service->submit($student, $assignment, 'My answers are attached in the text above. I found the second part the most interesting.');

                    if ($teacher !== null && mt_rand(1, 10) <= 7) {
                        $service->grade($teacher, $submission, (float) mt_rand(11, 20), 'Good effort. Check your working on question 3.');
                    }
                }
            }
        }

        return "{$count} assignments";
    }

    /* ---------------------------------------------------------------- finance */

    private function fees(): string
    {
        if (InvoiceBatch::query()->exists()) {
            return 'already there';
        }

        $students = Student::query()->with('guardians:id')->get();

        $families = $students->groupBy(fn (Student $student): int => (int) ($student->guardians->first()->id ?? 0));

        foreach ($families->filter(fn (Collection $siblings, int $guardianId): bool => $guardianId !== 0 && $siblings->count() > 1) as $siblings) {
            FeeConcession::query()->firstOrCreate(
                ['student_id' => $siblings->sortBy('date_of_birth')->last()->id],
                ['school_id' => $this->school->id, 'percent' => 10, 'reason' => 'Sibling discount'],
            );
        }

        foreach ($students->shuffle()->take(3) as $student) {
            FeeConcession::query()->firstOrCreate(['student_id' => $student->id], ['school_id' => $this->school->id, 'percent' => 25, 'reason' => 'Merit scholarship']);
        }

        $billing = app(BillingService::class);
        $items = FeeItem::query()->where('academic_year_id', $this->year->id)->get();
        $classIds = array_values(Section::query()->distinct()->pluck('school_class_id')->map(fn (mixed $id): int => (int) $id)->all());
        $tuition = array_values($items->where('name', 'Tuition fee')->map(fn (FeeItem $item): int => $item->id)->all());
        $annual = array_values($items->whereIn('name', ['Examination fee', 'Library & lab fee', 'Sports & activities'])->map(fn (FeeItem $item): int => $item->id)->all());

        $batches = [
            ['Annual & term fees 2026-27', '2026-04-05', '2026-04-25', $annual, 92],
            ['Tuition fee, September 2026', '2026-09-01', '2026-09-15', $tuition, 96],
            ['Tuition fee, October 2026', '2026-10-01', '2026-10-15', $tuition, 85],
            ['Tuition fee, November 2026', '2026-11-01', '2026-11-20', $tuition, 45],
        ];

        $methods = [PaymentMethod::BankTransfer, PaymentMethod::BankTransfer, PaymentMethod::Card, PaymentMethod::Cash, PaymentMethod::Online, PaymentMethod::Cheque];
        $invoices = 0;
        $payments = 0;

        foreach ($batches as [$title, $issued, $due, $itemIds, $paidPercent]) {
            $result = $billing->generateBatch($this->accountant, $this->year, $title, CarbonImmutable::parse($issued), CarbonImmutable::parse($due), $itemIds, $classIds);
            $invoices += $result['created'];

            foreach (Invoice::query()->where('invoice_batch_id', $result['batch']->id)->get() as $invoice) {
                $roll = mt_rand(1, 100);

                if ($roll > $paidPercent + 6) {
                    continue;
                }

                $amount = $roll > $paidPercent ? intdiv($invoice->balance(), 2) : $invoice->balance();
                $paidOn = CarbonImmutable::parse($due)->subDays(mt_rand(0, 12));

                if ($amount <= 0) {
                    continue;
                }

                $method = $methods[array_rand($methods)];
                $billing->recordPayment($this->accountant, $invoice, $amount, $method, $paidOn->min($this->today), $method === PaymentMethod::Cheque ? 'CHQ-'.mt_rand(100000, 999999) : null, null);
                $payments++;
            }
        }

        return "{$invoices} invoices, {$payments} payments";
    }

    /* --------------------------------------------------------------------- HR */

    private function hrActivity(): string
    {
        $payroll = app(PayrollService::class);
        $employees = Employee::query()->get();

        if (SalaryProfile::query()->doesntExist()) {
            foreach ($employees as $employee) {
                $basic = match (true) {
                    $employee->designation === 'Vice principal' => 650000,
                    $employee->is_teaching => mt_rand(420, 520) * 1000,
                    in_array($employee->designation, ['Accountant', 'IT administrator', 'School counsellor', 'School nurse', 'Librarian'], true) => mt_rand(380, 440) * 1000,
                    default => mt_rand(240, 320) * 1000,
                };

                $payroll->saveProfile($employee, $basic, [
                    ['name' => 'Housing allowance', 'amount' => intdiv($basic, 10)],
                    ['name' => 'Transport allowance', 'amount' => 15000],
                ], [
                    ['name' => 'Pension (5%)', 'amount' => intdiv($basic, 20)],
                    ['name' => 'Health insurance', 'amount' => 12000],
                ]);
            }
        }

        $leaves = app(LeaveService::class);
        $leaveCount = 0;

        if (LeaveRequest::query()->doesntExist()) {
            $types = LeaveType::query()->get();
            $reasons = ['Family wedding', 'Medical appointment', 'Feeling unwell', 'Personal matters', 'Child’s school event'];

            foreach ($employees->shuffle()->take(16) as $index => $employee) {
                $start = CarbonImmutable::parse('2026-09-07')->addWeekdays(mt_rand(0, 70));
                $end = $start->addWeekdays(mt_rand(0, 2));

                try {
                    $request = $leaves->request($employee, $types->random(), $start, $end, $reasons[array_rand($reasons)]);
                } catch (ValidationException) {
                    continue;
                }

                $leaveCount++;

                if ($index % 4 === 3) {
                    continue;
                }

                $leaves->review($this->hr, $request, $index % 7 === 5 ? LeaveStatus::Rejected : LeaveStatus::Approved, $index % 7 === 5 ? 'Exam week; please choose other dates.' : null);
            }
        }

        $runs = 0;

        foreach (['2026-08', '2026-09', '2026-10', '2026-11'] as $period) {
            if (PayrollRun::query()->where('period', $period)->exists()) {
                continue;
            }

            $run = $payroll->generate($this->hr, $this->school->id, $period);
            $runs++;

            if ($period === '2026-11') {
                continue;
            }

            $payroll->approve($this->principal, $run);
            $payroll->markPaid($this->accountant, $run, CarbonImmutable::parse("{$period}-01")->endOfMonth()->startOfDay());
        }

        return "{$employees->count()} salaries, {$leaveCount} leave requests, {$runs} payroll runs (".PayrollStatus::Draft->label().' for November)';
    }

    /* -------------------------------------------------------------- operations */

    private function transport(): string
    {
        if (TransportAssignment::query()->exists()) {
            return 'already there';
        }

        $service = app(TransportService::class);
        $routes = TransportRoute::query()->with('stops')->get();
        $count = 0;

        foreach (Student::query()->whereHas('enrollments')->inRandomOrder()->take(45)->get() as $student) {
            $route = $routes->random();

            try {
                $service->assign($student, $route, $route->stops->random()->id);
                $count++;
            } catch (ValidationException) {
                continue;
            }
        }

        return "{$count} riders";
    }

    /* ---------------------------------------------------------- communication */

    private function portalAndMessages(): string
    {
        $roles = Role::query()->whereNull('school_id')->pluck('id', 'slug');
        $portal = 0;
        $alreadySeeded = Guardian::query()->whereNotNull('user_id')->count() > 1;

        foreach ($alreadySeeded ? [] : Guardian::query()->whereNull('user_id')->whereNotNull('email')->orderBy('id')->take(20)->get() as $guardian) {
            $portal += $this->portalUser($guardian, (string) $guardian->email, $roles['parent']) ? 1 : 0;
        }

        foreach ($alreadySeeded ? [] : Student::query()->whereNull('user_id')->whereNotNull('email')->orderBy('id')->take(12)->get() as $student) {
            $portal += $this->portalUser($student, (string) $student->email, $roles['student']) ? 1 : 0;
        }

        if (Conversation::query()->exists()) {
            return "{$portal} portal accounts";
        }

        $messaging = app(MessagingService::class);
        $parents = User::query()->whereHas('guardian')->get();
        $threads = 0;

        foreach ($parents->take(8) as $parent) {
            $studentIds = Guardian::query()->where('user_id', $parent->id)->first()?->students()->pluck('students.id') ?? collect();
            $sectionId = Enrollment::query()->where('academic_year_id', $this->year->id)->whereIn('student_id', $studentIds)->value('section_id');
            $teacherId = Section::query()->whereKey($sectionId)->value('class_teacher_id');
            $teacher = $teacherId !== null ? User::query()->whereKey($teacherId)->first() : null;

            if ($teacher === null) {
                continue;
            }

            $conversation = $messaging->start($teacher, [$parent->id], 'Progress this term', "Hello {$parent->name}, just a quick note to say your child has settled in well this term. Mid-term results are now in the portal; let me know if you would like to meet.");
            $messaging->post($conversation, $parent, 'Thank you for the update. Could we meet at the parent–teacher evening?');
            $messaging->post($conversation, $teacher, 'Of course. I will keep a slot for you on the 19th.');
            $threads++;
        }

        $staff = array_values(User::query()->whereHas('employee')->where('id', '!=', $this->principal->id)->take(6)->get()->map(fn (User $user): int => $user->id)->all());

        if ($staff !== []) {
            $messaging->start($this->principal, $staff, 'Sports day volunteers', 'We need six staff volunteers for sports day. Please reply if you can help with the track events.');
            $threads++;
        }

        return "{$portal} portal accounts, {$threads} conversations";
    }

    private function portalUser(Student|Guardian $person, string $email, int $roleId): bool
    {
        if (User::query()->where('email', $email)->exists()) {
            return false;
        }

        $account = User::query()->create([
            'name' => $person->full_name,
            'email' => $email,
            'password' => 'password',
            'school_id' => $this->school->id,
        ]);
        $account->forceFill(['email_verified_at' => now()])->save();
        $account->roles()->syncWithoutDetaching([$roleId => ['created_at' => now()]]);
        $person->update(['user_id' => $account->id]);

        return true;
    }

    private function records(): string
    {
        $documents = 0;

        if (Document::query()->doesntExist()) {
            foreach ([
                ['School calendar 2026-27', 'Calendar', Audience::Everyone, "Greenfield High School calendar 2026-27\n\nTerm 1: 1 April – 30 September 2026\nTerm 2: 1 October 2026 – 31 March 2027\nWinter break: 23 December – 3 January\n"],
                ['Uniform policy', 'Policy', Audience::Guardians, "Uniform policy\n\nSummer uniform: April – November.\nWinter uniform including the school blazer: December – February.\n"],
                ['Bus routes and timings', 'Transport', Audience::Everyone, "Bus routes\n\nNorth loop: Maple Avenue 07:05, Oak Park 07:15, Riverside Mall 07:25.\nEast loop: Cedar Heights 07:00, Station Road 07:12, Hillview 07:24.\n"],
                ['Mid-term exam timetable', 'Examinations', Audience::Students, "Mid-term examination timetable\n\n14 – 25 September 2026. Papers start at 09:00.\n"],
                ['Staff handbook', 'Policy', Audience::Staff, "Staff handbook\n\nArrival by 07:40. Registers are taken in period 1. Leave requests go through the HR module.\n"],
            ] as [$title, $category, $audience, $content]) {
                $path = "documents/{$this->school->id}/".Str::random(40).'.txt';
                Storage::disk('local')->put($path, $content);

                Document::query()->create([
                    'school_id' => $this->school->id,
                    'title' => $title,
                    'category' => $category,
                    'audience' => $audience,
                    'path' => $path,
                    'original_name' => Str::slug($title).'.txt',
                    'mime_type' => 'text/plain',
                    'size' => strlen($content),
                    'uploaded_by' => $this->admin->id,
                ]);
                $documents++;
            }
        }

        $certificates = 0;

        if (Certificate::query()->doesntExist()) {
            $service = app(CertificateService::class);
            $types = [CertificateType::Bonafide, CertificateType::Character, CertificateType::Achievement, CertificateType::Bonafide, CertificateType::Character, CertificateType::Achievement, CertificateType::Bonafide];

            foreach (Student::query()->whereHas('enrollments')->inRandomOrder()->take(count($types))->get() as $index => $student) {
                $certificate = $service->draft($student, $types[$index], $this->admin);
                $certificates++;

                if ($index < 5) {
                    $service->issue($certificate, $this->principal);
                }

                if ($index === 4) {
                    $service->revoke($certificate, 'Issued with the wrong date; replaced.');
                }
            }
        }

        return "{$documents} documents, {$certificates} certificates";
    }

    /* ---------------------------------------------------------------- library */

    private function library(): string
    {
        if (LibraryBook::query()->doesntExist()) {
            return 'skipped: import 4-library-books.xlsx, then run this seeder again';
        }

        if (LibraryLoan::query()->exists()) {
            return 'already there';
        }

        $service = app(LibraryService::class);
        $books = LibraryBook::query()->where('copies_available', '>', 0)->get();
        $students = Student::query()->whereHas('enrollments')->inRandomOrder()->take(40)->get();
        $loans = 0;

        foreach ($students as $index => $student) {
            $book = $books->random();

            [$dueOn, $return] = match (true) {
                $index < 18 => [$this->today->addDays(mt_rand(3, 10)), true],
                $index < 24 => [$this->today->subDays(mt_rand(2, 9)), true],
                $index < 30 => [$this->today->subDays(mt_rand(1, 12)), false],
                default => [null, false],
            };

            try {
                $loan = $service->issue($book, $student, $this->librarian, $dueOn);
            } catch (ValidationException) {
                continue;
            }

            $issuedOn = ($dueOn ?? $this->today)->subDays(14);
            $loan->forceFill(['issued_on' => $issuedOn->min($this->today)])->save();
            $loans++;

            if ($return) {
                $service->return($loan, $this->librarian);
            }
        }

        foreach (Employee::query()->where('is_teaching', true)->inRandomOrder()->take(5)->get() as $employee) {
            try {
                $service->issue($books->random(), $employee, $this->librarian);
                $loans++;
            } catch (ValidationException) {
                continue;
            }
        }

        return "{$loans} loans";
    }

    /* --------------------------------------------------------------- learning */

    private function learning(): string
    {
        if (Course::query()->exists()) {
            return 'already there';
        }

        $plans = [
            'ENG' => ['Reading for meaning', [['Stories and characters', ['Who is telling the story?', 'Describing characters', 'Beginning, middle and end']], ['Words and sentences', ['Nouns and verbs', 'Joining sentences', 'Using full stops and capitals']]]],
            'MATH' => ['Number sense and fractions', [['Place value', ['Hundreds, tens and ones', 'Comparing numbers', 'Rounding']], ['Fractions', ['What is a fraction?', 'Equivalent fractions', 'Adding simple fractions']]]],
            'SCI' => ['Living things and energy', [['Plants', ['Parts of a plant', 'How plants make food', 'Life cycle of a plant']], ['Energy', ['Forms of energy', 'Light and shadows', 'Saving energy at home']]]],
            'PHY' => ['Forces and motion', [['Motion', ['Speed and velocity', 'Acceleration', 'Distance–time graphs']], ['Forces', ['Newton’s first law', 'Friction', 'Balanced and unbalanced forces']]]],
        ];
        $links = [
            ['Watch: short explainer video', 'https://www.youtube.com/results?search_query='],
            ['Practise online', 'https://www.khanacademy.org/search?page_search_query='],
        ];

        $classes = SchoolClass::query()->with(['subjects', 'sections'])->whereIn('level', [1, 5, 8, 10])->get();
        $courses = 0;
        $lessons = 0;

        foreach ($classes as $class) {
            foreach ($class->subjects->whereIn('code', array_keys($plans))->take(2) as $subject) {
                [$title, $chapters] = $plans[$subject->code];
                $section = $class->sections->first();
                $teacherId = TimetableEntry::query()->where('section_id', $section?->id)->where('subject_id', $subject->id)->value('teacher_id') ?? $section?->class_teacher_id;

                $course = Course::query()->create([
                    'school_id' => $this->school->id,
                    'academic_year_id' => $this->year->id,
                    'school_class_id' => $class->id,
                    'subject_id' => $subject->id,
                    'title' => "{$title} ({$class->name})",
                    'description' => "Core {$subject->name} topics for {$class->name}, with notes, videos and practice.",
                    'status' => PublishStatus::Published,
                    'created_by' => $teacherId,
                ]);
                $courses++;

                foreach ($chapters as $chapterIndex => [$chapterTitle, $lessonTitles]) {
                    $chapter = $course->chapters()->create(['school_id' => $this->school->id, 'title' => $chapterTitle, 'position' => $chapterIndex + 1]);

                    foreach ($lessonTitles as $lessonIndex => $lessonTitle) {
                        $published = ! ($chapterIndex === 1 && $lessonIndex === 2);
                        $lesson = Lesson::query()->create([
                            'school_id' => $this->school->id,
                            'course_id' => $course->id,
                            'course_chapter_id' => $chapter->id,
                            'title' => $lessonTitle,
                            'summary' => 'In this lesson you will learn about '.Str::lower($lessonTitle).'.',
                            'body' => "Read the notes, watch the short video, then try the practice questions.\n\nKey idea: ".Str::lower($lessonTitle).' builds on what you learned in the previous lesson. Write down one question you still have and bring it to class.',
                            'estimated_minutes' => [15, 20, 25, 30][array_rand([15, 20, 25, 30])],
                            'status' => $published ? PublishStatus::Published : PublishStatus::Draft,
                            'notified_at' => $published ? now() : null,
                            'position' => $lessonIndex + 1,
                            'created_by' => $teacherId,
                        ]);
                        $lessons++;

                        foreach ($links as $position => [$label, $url]) {
                            LessonMaterial::query()->create([
                                'school_id' => $this->school->id,
                                'lesson_id' => $lesson->id,
                                'kind' => MaterialKind::Link,
                                'title' => $label,
                                'url' => $url.urlencode($lessonTitle),
                                'is_downloadable' => false,
                                'position' => $position + 1,
                                'uploaded_by' => $teacherId,
                            ]);
                        }
                    }
                }

                $this->learningActivity($course, $teacherId);
            }
        }

        return "{$courses} courses, {$lessons} lessons";
    }

    private function learningActivity(Course $course, ?int $teacherId): void
    {
        $lessonIds = Lesson::query()->where('course_id', $course->id)->where('status', PublishStatus::Published)->orderBy('course_chapter_id')->orderBy('position')->pluck('id');
        $students = Enrollment::query()->where('academic_year_id', $this->year->id)
            ->whereHas('section', fn ($query) => $query->where('school_class_id', $course->school_class_id))->pluck('student_id');

        foreach ($students as $studentId) {
            $reach = mt_rand(0, $lessonIds->count());

            foreach ($lessonIds->take($reach) as $index => $lessonId) {
                $viewed = $this->today->subDays(mt_rand(0, 20))->setTime(mt_rand(15, 20), mt_rand(0, 59));
                LessonProgress::query()->create([
                    'school_id' => $this->school->id,
                    'lesson_id' => $lessonId,
                    'student_id' => $studentId,
                    'first_viewed_at' => $viewed,
                    'last_viewed_at' => $viewed,
                    'completed_at' => $index < $reach - 1 || mt_rand(0, 1) === 1 ? $viewed : null,
                    'views' => mt_rand(1, 4),
                ]);
            }
        }

        LiveClass::query()->create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $course->school_class_id,
            'subject_id' => $course->subject_id,
            'course_id' => $course->id,
            'title' => 'Live revision: '.Str::before($course->title, ' ('),
            'description' => 'Bring your questions from this week’s lessons.',
            'provider' => MeetingProvider::GoogleMeet,
            'meeting_url' => 'https://meet.google.com/demo-'.Str::lower(Str::random(3)).'-'.Str::lower(Str::random(4)),
            'starts_at' => $this->today->addDays(mt_rand(1, 6))->setTime(mt_rand(15, 17), 0),
            'duration_minutes' => 45,
            'status' => LiveClassStatus::Scheduled,
            'created_by' => $teacherId,
        ]);

        $quiz = Quiz::query()->create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $course->school_class_id,
            'subject_id' => $course->subject_id,
            'course_id' => $course->id,
            'type' => QuizType::Quiz,
            'title' => 'Check your understanding: '.Str::before($course->title, ' ('),
            'instructions' => 'Answer every question. You have one attempt.',
            'closes_at' => $this->today->addDays(10)->setTime(23, 59),
            'max_attempts' => 1,
            'show_results' => true,
            'status' => PublishStatus::Published,
            'notified_at' => now(),
            'created_by' => $teacherId,
        ]);

        $questions = [
            $quiz->questions()->create(['school_id' => $this->school->id, 'type' => 'single_choice', 'prompt' => 'Which statement best matches the first lesson?', 'options' => [['id' => 'a', 'text' => 'It introduces the main idea'], ['id' => 'b', 'text' => 'It has no main idea'], ['id' => 'c', 'text' => 'It is only about spelling']], 'answer_key' => ['a'], 'marks' => 2, 'position' => 1]),
            $quiz->questions()->create(['school_id' => $this->school->id, 'type' => 'true_false', 'prompt' => 'Each lesson builds on the one before it.', 'answer_key' => ['true'], 'marks' => 1, 'position' => 2]),
            $quiz->questions()->create(['school_id' => $this->school->id, 'type' => 'essay', 'prompt' => 'In two or three sentences, explain one thing you learned.', 'marks' => 3, 'position' => 3]),
        ];

        $service = app(QuizService::class);
        $teacher = $teacherId !== null ? User::query()->whereKey($teacherId)->first() : null;

        foreach (Student::query()->whereIn('id', $students->shuffle()->take(max(1, intdiv($students->count(), 2))))->get() as $student) {
            $attempt = $service->start($student, $quiz);
            $attempt = $service->submit($attempt, [
                $questions[0]->id => mt_rand(1, 4) === 1 ? 'b' : 'a',
                $questions[1]->id => mt_rand(1, 5) === 1 ? 'false' : 'true',
                $questions[2]->id => 'I learned how the main idea connects the lessons together.',
            ]);

            if ($teacher !== null && mt_rand(0, 1) === 1) {
                $essay = $attempt->answers()->where('quiz_question_id', $questions[2]->id)->first();

                if ($essay !== null) {
                    $service->grade($teacher, $attempt, [$essay->id => ['marks' => mt_rand(1, 3), 'feedback' => 'Clear explanation.']], 'Good effort.');
                }
            }
        }
    }

    /* --------------------------------------------------------- administration */

    private function administration(): string
    {
        if (ContactInquiry::query()->doesntExist()) {
            ContactInquiry::factory()->count(5)->create();
            ContactInquiry::factory()->read()->count(4)->create();
        }

        WebhookEndpoint::query()->firstOrCreate(
            ['school_id' => $this->school->id, 'url' => 'https://hooks.example.com/greenfield/erp'],
            [
                'description' => 'Demo endpoint (disabled)',
                'secret' => 'whsec_'.Str::random(32),
                'events' => ['student.created', 'payment.recorded'],
                'is_active' => false,
                'created_by' => $this->admin->id,
            ],
        );

        return 'done';
    }
}
