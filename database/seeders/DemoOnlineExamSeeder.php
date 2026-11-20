<?php

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Enums\QuestionType;
use App\Enums\QuizAttemptStatus;
use App\Models\AcademicYear;
use App\Models\BankQuestion;
use App\Models\Enrollment;
use App\Models\OnlineExam;
use App\Models\OnlineExamAttempt;
use App\Models\OnlineExamQuestion;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Academics\Exams\OnlineExamResults;
use App\Services\Academics\Exams\OnlineExamService;
use App\Services\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Demo online exams for Greenfield High School, Class 8 (the demo student Aiden
 * Martinez is in 8 A): a question bank, an exam with published results, one waiting
 * for essays to be marked, one open now and one coming up next week.
 *
 * Attempts go through the real services (start, hand in, mark, approve, publish) so
 * the data matches what the app produces. Run on its own with
 * `php artisan db:seed --class=DemoOnlineExamSeeder`; running it again changes nothing.
 */
class DemoOnlineExamSeeder extends Seeder
{
    /**
     * @var array<string, list<array{0: string, 1: string, 2: list<string>|string|null, 3: list<int>|bool|list<string>|null, 4: float, 5?: string}>>
     */
    private const array BANK = [
        'Mathematics' => [
            ['single_choice', 'What is 3/4 written as a decimal?', ['0.34', '0.75', '0.43', '7.5'], [1], 2, 'Fractions'],
            ['single_choice', 'Which fraction is equal to 0.2?', ['1/2', '2/10', '1/20', '2/5'], [1], 2, 'Fractions'],
            ['multiple_choice', 'Which of these numbers are prime?', ['9', '11', '15', '17'], [1, 3], 3, 'Number'],
            ['single_choice', 'What is 15% of 200?', ['15', '20', '30', '35'], [2], 2, 'Percentages'],
            ['true_false', 'The sum of the angles in a triangle is 180 degrees.', null, true, 1, 'Geometry'],
            ['single_choice', 'Solve for x: 2x + 6 = 14', ['3', '4', '5', '10'], [1], 2, 'Algebra'],
            ['short_answer', 'What is the square root of 81?', null, ['9', 'nine'], 2, 'Number'],
            ['true_false', '0.5 is greater than 0.45.', null, true, 1, 'Decimals'],
            ['single_choice', 'Round 3.468 to one decimal place.', ['3.4', '3.5', '3.47', '3.0'], [1], 2, 'Decimals'],
            ['essay', 'Explain, with an example, how to compare two fractions with different denominators.', null, null, 4, 'Fractions'],
        ],
        'Science' => [
            ['single_choice', 'Which part of the cell controls its activities?', ['Cell wall', 'Nucleus', 'Cytoplasm', 'Vacuole'], [1], 2, 'Cells'],
            ['multiple_choice', 'Which of these are found in plant cells but not animal cells?', ['Cell wall', 'Chloroplasts', 'Nucleus', 'Cell membrane'], [0, 1], 3, 'Cells'],
            ['true_false', 'Photosynthesis releases oxygen.', null, true, 1, 'Plants'],
            ['single_choice', 'What do we call an animal that eats only plants?', ['Carnivore', 'Omnivore', 'Herbivore', 'Predator'], [2], 2, 'Living things'],
            ['short_answer', 'What gas do plants take in for photosynthesis?', null, ['carbon dioxide', 'co2'], 2, 'Plants'],
            ['essay', 'Describe how a plant makes its own food. Use the words sunlight, water and carbon dioxide.', null, null, 5, 'Plants'],
            ['true_false', 'All living things are made of cells.', null, true, 1, 'Cells'],
        ],
        'English' => [
            ['single_choice', 'Which word is a synonym of "happy"?', ['Sad', 'Joyful', 'Angry', 'Tired'], [1], 2, 'Vocabulary'],
            ['single_choice', 'Choose the correct spelling.', ['Recieve', 'Receive', 'Receeve', 'Reseive'], [1], 2, 'Spelling'],
            ['single_choice', 'What is the opposite of "ancient"?', ['Old', 'Modern', 'Broken', 'Large'], [1], 2, 'Vocabulary'],
            ['true_false', '"Quickly" is an adverb.', null, true, 2, 'Grammar'],
            ['short_answer', 'Write the plural of "child".', null, ['children'], 2, 'Grammar'],
        ],
    ];

    public function run(CurrentSchool $currentSchool, OnlineExamService $exams, OnlineExamResults $results): void
    {
        $school = School::query()->where('code', 'GHS')->first();

        if ($school === null) {
            $this->command?->warn('Demo school GHS not found. Run the DemoSeeder first.');

            return;
        }

        $currentSchool->set($school);
        $year = AcademicYear::query()->current()->first();
        $class = SchoolClass::query()->where('name', 'Class 8')->first();

        if ($year === null || $class === null || Enrollment::query()->where('academic_year_id', $year->id)->doesntExist()) {
            $this->command?->warn('No enrolled students yet. Import 1-students-and-parents.xlsx first.');

            return;
        }

        if (OnlineExam::query()->exists()) {
            $this->command?->info('Online exams already exist; nothing to do.');

            return;
        }

        mt_srand(42);
        $teacherId = Section::query()->where('school_class_id', $class->id)->orderBy('name')->value('class_teacher_id');
        $author = User::query()->whereKey((int) $teacherId)->first()
            ?? User::query()->where('email', 'school.admin@example.com')->firstOrFail();
        $subjects = Subject::query()->whereIn('name', array_keys(self::BANK))->get()->keyBy('name');

        $bank = $this->bank($school, $class, $subjects, $author);
        $students = Enrollment::query()
            ->with('student')
            ->where('academic_year_id', $year->id)
            ->where('status', EnrollmentStatus::Active)
            ->whereHas('section', fn ($query) => $query->where('school_class_id', $class->id))
            ->get()
            ->pluck('student')
            ->filter()
            ->values();
        $today = CarbonImmutable::now();

        // 1. Ended ten days ago; marked automatically, approved and published.
        $published = $this->exam($school, $year, $class, $subjects['Mathematics'], $author, $bank['Mathematics']->take(9), [
            'title' => 'Unit test 1: Fractions and decimals',
            'instructions' => "Answer every question. Wrong answers lose a quarter of the question's marks, so leave a question blank if you are unsure.\nCalculators are not allowed.",
            'duration_minutes' => 30,
            'passing_marks' => 7,
            'negative_marking' => true,
            'show_rank' => true,
        ]);
        $this->sit($exams, $published, $students, 0.95);
        $this->moveToPast($published, $today->subDays(10)->setTime(9, 0));
        $results->approve($published, $author);
        $results->publish($published, $author);

        // 2. Ended two days ago; essays partly marked.
        $marking = $this->exam($school, $year, $class, $subjects['Science'], $author, $bank['Science'], [
            'title' => 'Science check: Cells and plants',
            'instructions' => 'Answer all questions. The last question is a short written answer.',
            'duration_minutes' => 40,
            'passing_marks' => 8,
        ]);
        $this->sit($exams, $marking, $students->take(9), 1.0);
        $this->moveToPast($marking, $today->subDays(2)->setTime(10, 30));
        $this->markSomeEssays($exams, $marking, $author);

        // 3. Open now for a week: students (including Aiden Martinez) can sit it.
        $this->exam($school, $year, $class, $subjects['Mathematics'], $author, $bank['Mathematics']->slice(2), [
            'title' => 'Mathematics practice exam (online)',
            'instructions' => 'A mixed paper: multiple choice, a short answer and one written question. You have 45 minutes once you start.',
            'duration_minutes' => 45,
            'passing_marks' => 8,
            'max_attempts' => 2,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'starts_at' => $today->subHour(),
            'ends_at' => $today->addDays(7)->setTime(18, 0),
        ]);

        // 4. Scheduled next week.
        $this->exam($school, $year, $class, $subjects['English'], $author, $bank['English'], [
            'title' => 'English vocabulary quiz',
            'duration_minutes' => 20,
            'passing_marks' => 5,
            'shuffle_options' => true,
            'starts_at' => $today->addDays(7)->setTime(9, 0),
            'ends_at' => $today->addDays(7)->setTime(12, 0),
        ]);

        $this->command?->info('Online exams: 4 exams for Class 8 (one with published results, one awaiting marking, one open now, one scheduled) and '.$bank->flatten()->count().' bank questions.');
    }

    /**
     * @param  Collection<string, Subject>  $subjects
     * @return Collection<string, Collection<int, BankQuestion>>
     */
    private function bank(School $school, SchoolClass $class, Collection $subjects, User $author): Collection
    {
        $bank = [];

        foreach (self::BANK as $subjectName => $items) {
            $subject = $subjects->get($subjectName);
            $created = [];

            foreach ($subject === null ? [] : $items as $index => $item) {
                [$type, $prompt, $options, $key, $marks] = $item;
                $optionList = null;
                $answerKey = null;

                if (is_array($options)) {
                    $optionList = [];
                    $answerKey = [];

                    foreach ($options as $position => $text) {
                        $optionList[] = ['id' => chr(97 + $position), 'text' => $text];
                    }

                    foreach ((array) $key as $position) {
                        $answerKey[] = chr(97 + (int) $position);
                    }
                } elseif ($type === QuestionType::TrueFalse->value) {
                    $answerKey = [$key === true ? 'true' : 'false'];
                } elseif (is_array($key)) {
                    $answerKey = array_map(strval(...), $key);
                }

                $created[] = BankQuestion::query()->create([
                    'school_id' => $school->id,
                    'subject_id' => $subject->id,
                    'school_class_id' => $index % 3 === 0 ? null : $class->id,
                    'type' => $type,
                    'difficulty' => ['easy', 'medium', 'medium', 'hard'][$index % 4],
                    'prompt' => $prompt,
                    'options' => $optionList,
                    'answer_key' => $answerKey,
                    'marks' => $marks,
                    'negative_marks' => in_array($type, ['single_choice', 'multiple_choice', 'true_false'], true) ? $marks / 4 : 0,
                    'topic' => $item[5],
                    'created_by' => $author->id,
                ]);
            }

            $bank[$subjectName] = collect($created);
        }

        return collect($bank);
    }

    /**
     * A published exam whose questions are copied from the bank, open now unless times are given.
     *
     * @param  Collection<int, BankQuestion>  $questions
     * @param  array<string, mixed>  $settings
     */
    private function exam(School $school, AcademicYear $year, SchoolClass $class, Subject $subject, User $author, Collection $questions, array $settings): OnlineExam
    {
        $exam = OnlineExam::query()->create([
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHours(3),
            'total_marks' => $questions->sum(fn (BankQuestion $question): float => (float) $question->marks),
            'max_attempts' => 1,
            'created_by' => $author->id,
            ...$settings,
        ]);

        foreach ($questions->values() as $position => $item) {
            OnlineExamQuestion::query()->create([
                'school_id' => $school->id,
                'online_exam_id' => $exam->id,
                'bank_question_id' => $item->id,
                'type' => $item->type,
                'prompt' => $item->prompt,
                'options' => $item->options,
                'answer_key' => $item->answer_key,
                'marks' => $item->marks,
                'negative_marks' => $exam->negative_marking ? $item->negative_marks : 0,
                'explanation' => $item->explanation,
                'position' => $position + 1,
            ]);
        }

        app(OnlineExamService::class)->publish($exam);
        $exam->forceFill(['notified_at' => now()])->saveQuietly();

        return $exam;
    }

    /**
     * Every student sits the exam, answering with their own (seeded) ability.
     *
     * @param  Collection<int, Student>  $students
     */
    private function sit(OnlineExamService $exams, OnlineExam $exam, Collection $students, float $turnout): void
    {
        $questions = $exam->questions()->get();

        foreach ($students as $student) {
            if (mt_rand(1, 100) > $turnout * 100) {
                continue;
            }

            $ability = mt_rand(35, 98) / 100;
            $attempt = $exams->start($student, $exam);
            $answers = [];

            foreach ($questions as $question) {
                if (mt_rand(1, 100) <= 6) {
                    continue;
                }

                $answers[$question->id] = $this->answer($question, mt_rand(1, 100) <= $ability * 100);
            }

            $exams->submit($attempt->setRelation('exam', $exam), $answers);
        }
    }

    /**
     * @return list<string>|string
     */
    private function answer(OnlineExamQuestion $question, bool $right): array|string
    {
        $key = $question->answer_key ?? [];
        $optionIds = array_column($question->options ?? [], 'id');
        $wrong = array_values(array_diff($optionIds, $key));

        return match ($question->type) {
            QuestionType::SingleChoice => $right ? $key[0] : $wrong[array_rand($wrong)],
            QuestionType::MultipleChoice => $right ? $key : [$key[0], $wrong[0]],
            QuestionType::TrueFalse => $right ? $key[0] : ($key[0] === 'true' ? 'false' : 'true'),
            QuestionType::ShortAnswer => $right ? $key[0] : 'not sure',
            QuestionType::Essay => match (true) {
                ! $right => 'I am not completely sure, but I think it depends on which number is bigger.',
                str_contains($question->prompt, 'fraction') => 'Find a common denominator first, then compare the numerators. For example, 2/3 and 3/4 become 8/12 and 9/12, so 3/4 is bigger.',
                default => 'Leaves trap sunlight. The plant takes in water through its roots and carbon dioxide through its leaves, and uses the light energy to turn them into glucose, giving out oxygen.',
            },
        };
    }

    /**
     * Shift an exam and its attempts back in time so it reads as already sat.
     */
    private function moveToPast(OnlineExam $exam, CarbonImmutable $startsAt): void
    {
        $shift = (int) round(now()->diffInSeconds($startsAt, false));
        $exam->forceFill(['starts_at' => $startsAt, 'ends_at' => $startsAt->addHours(3)])->save();

        foreach ($exam->attempts()->get() as $index => $attempt) {
            $started = $attempt->started_at->addSeconds($shift)->addMinutes($index % 20);
            $attempt->forceFill([
                'started_at' => $started,
                'deadline_at' => $started->addMinutes($exam->duration_minutes),
                'submitted_at' => $started->addMinutes(mt_rand(10, $exam->duration_minutes - 1)),
                'graded_at' => $attempt->graded_at !== null ? $started->addMinutes($exam->duration_minutes) : null,
            ])->save();
        }
    }

    /**
     * The teacher has marked about half of the written answers.
     */
    private function markSomeEssays(OnlineExamService $exams, OnlineExam $exam, User $teacher): void
    {
        $attempts = $exam->attempts()->where('status', QuizAttemptStatus::Submitted)->with('answers.question')->get();

        foreach ($attempts->take((int) ceil($attempts->count() / 2)) as $attempt) {
            /** @var OnlineExamAttempt $attempt */
            $marks = [];

            foreach ($attempt->answers as $answer) {
                if ($answer->marks_awarded === null && $answer->answer !== null) {
                    $max = (float) $answer->question->marks;
                    $marks[$answer->id] = ['marks' => round(mt_rand(40, 100) / 100 * $max * 2) / 2, 'feedback' => 'Good explanation. Add a worked example next time.'];
                }
            }

            $exams->grade($teacher, $attempt->setRelation('exam', $exam), $marks, 'Solid effort overall.');
        }
    }
}
