<?php

namespace App\Console\Commands;

use App\Services\Import\ImportColumn;
use App\Services\Import\Importer;
use App\Services\Import\Importers\AdmissionImporter;
use App\Services\Import\Importers\LibraryBookImporter;
use App\Services\Import\Importers\StaffImporter;
use App\Services\Import\Importers\StudentImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes ready-to-import demo files for the Greenfield demo school: 100 students with
 * parents, the staff list, admission applications and 100 library books. Headers come
 * from the importers, so the files always match the templates. Output is the same on
 * every run.
 */
class GenerateDemoSpreadsheets extends Command
{
    protected $signature = 'demo:spreadsheets {--path= : Folder to write to (default storage/app/demo-imports)}';

    protected $description = 'Generate demo Excel files for the student, staff, admission and library book imports';

    private const array BOYS = ['Liam', 'Noah', 'Oliver', 'Elijah', 'James', 'Lucas', 'Mateo', 'Ethan', 'Aiden', 'Arjun', 'Omar', 'Yusuf', 'Kenji', 'Leo', 'Samuel', 'Daniel', 'Isaac', 'Gabriel', 'Ryan', 'Adam', 'Zain', 'Hiro', 'Diego', 'Kofi', 'Nathan', 'Caleb', 'Ayaan', 'Rohan', 'Felix', 'Hugo'];

    private const array GIRLS = ['Olivia', 'Emma', 'Amelia', 'Sophia', 'Mia', 'Ava', 'Isla', 'Zara', 'Aisha', 'Priya', 'Hana', 'Sakura', 'Lucia', 'Chloe', 'Grace', 'Layla', 'Maya', 'Nora', 'Ella', 'Leah', 'Fatima', 'Amara', 'Sofia', 'Ivy', 'Ruby', 'Anaya', 'Mei', 'Elena', 'Clara', 'Nadia'];

    private const array SURNAMES = ['Smith', 'Johnson', 'Williams', 'Brown', 'Garcia', 'Miller', 'Davis', 'Martinez', 'Lopez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Martin', 'Lee', 'Thompson', 'White', 'Harris', 'Clark', 'Lewis', 'Walker', 'Hall', 'Young', 'King', 'Wright', 'Scott', 'Green', 'Baker', 'Adams', 'Nelson', 'Hill', 'Campbell', 'Mitchell', 'Roberts', 'Carter', 'Patel', 'Khan', 'Nguyen', 'Kim', 'Tanaka', 'Okafor', 'Mensah', 'Ahmed', 'Singh', 'Rossi', 'Novak', 'Silva', 'Haddad', 'Cohen'];

    private const array STREETS = ['Maple Avenue', 'Oak Park Road', 'Cedar Lane', 'Elm Court', 'Pine Street', 'Birch Way', 'Lakeside Drive', 'Hillview Road', 'Station Road', 'Riverside Walk', 'Westgate Close', 'Willow Crescent'];

    private const array BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'O+', 'O-', 'O+', 'A+', 'B+'];

    private const array OCCUPATIONS = ['Engineer', 'Nurse', 'Teacher', 'Accountant', 'Doctor', 'Shop owner', 'Software developer', 'Architect', 'Pharmacist', 'Electrician', 'Civil servant', 'Lawyer', 'Chef', 'Designer', 'Police officer', 'Farmer', 'Banker', 'Homemaker'];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Demo files are not generated in production.');

            return self::FAILURE;
        }

        $directory = (string) ($this->option('path') ?: storage_path('app/demo-imports'));
        File::ensureDirectoryExists($directory);

        mt_srand(2026);

        $files = [
            '1-students-and-parents.xlsx' => [StudentImporter::class, $this->students()],
            '2-staff.xlsx' => [StaffImporter::class, $this->staff()],
            '3-admission-applications.xlsx' => [AdmissionImporter::class, $this->admissions()],
            '4-library-books.xlsx' => [LibraryBookImporter::class, $this->books()],
        ];

        foreach ($files as $name => [$importerClass, $rows]) {
            $path = $directory.DIRECTORY_SEPARATOR.$name;
            $this->write(app($importerClass), $rows, $path);
            $this->line(sprintf('  %-34s %3d rows', $name, count($rows)));
        }

        $this->info("Written to {$directory}");

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, string|int|null>>  $rows
     */
    private function write(Importer $importer, array $rows, string $path): void
    {
        $columns = $importer->columns();
        $header = (new Style)->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor('1E3A8A');

        $writer = new Writer;
        $writer->openToFile($path);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Data');
        $sheet->setColumnWidthForRange(20, 1, max(1, count($columns)));

        $writer->addRow(Row::fromValuesWithStyle(array_map(fn (ImportColumn $column): string => $column->header(), $columns), $header));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(fn (ImportColumn $column): string|int|null => $row[$column->key] ?? null, $columns)));
        }

        $writer->close();
    }

    /**
     * 100 students in Class 1–10 (five per section). Twenty families have two children,
     * so those siblings share one parent row (same phone and email).
     *
     * @return list<array<string, string|int|null>>
     */
    private function students(): array
    {
        $families = [];

        foreach (range(1, 80) as $family) {
            $surname = self::SURNAMES[($family * 7) % count(self::SURNAMES)];
            $parentIsMother = mt_rand(0, 1) === 1;
            $parentFirst = $this->pick($parentIsMother ? self::GIRLS : self::BOYS);

            $families[] = [
                'surname' => $surname,
                'parent_first_name' => $parentFirst,
                'parent_relationship' => $parentIsMother ? 'Mother' : 'Father',
                'parent_phone' => sprintf('+1 555 %03d %04d', 200 + intdiv($family, 100), 1000 + $family),
                'parent_email' => Str::lower("{$parentFirst}.{$surname}{$family}@example.com"),
                'parent_occupation' => $this->pick(self::OCCUPATIONS),
                'address' => mt_rand(10, 480).' '.$this->pick(self::STREETS).', Springfield',
                'children' => $family <= 20 ? 2 : 1,
            ];
        }

        $children = [];

        foreach ($families as $family) {
            for ($child = 0; $child < $family['children']; $child++) {
                $children[] = $family;
            }
        }

        $rows = [];
        $medical = ['Mild asthma; inhaler kept in the nurse’s office.', 'Peanut allergy.', 'Wears glasses for reading.', 'Lactose intolerant.'];

        foreach ($children as $index => $family) {
            $level = intdiv($index, 10) + 1;
            $section = $index % 10 < 5 ? 'A' : 'B';
            $isGirl = mt_rand(0, 1) === 1;
            $first = $this->pick($isGirl ? self::GIRLS : self::BOYS);
            $birthYear = 2026 - 5 - $level - mt_rand(0, 1);
            $joinedYear = max($birthYear + 5, 2026 - mt_rand(0, min(6, $level)));

            $rows[] = [
                'first_name' => $first,
                'last_name' => $family['surname'],
                'gender' => $isGirl ? 'Female' : 'Male',
                'date_of_birth' => sprintf('%04d-%02d-%02d', $birthYear, mt_rand(1, 12), mt_rand(1, 28)),
                'admission_date' => sprintf('%04d-04-%02d', $joinedYear, mt_rand(1, 10)),
                'class' => "Class {$level}",
                'section' => $section,
                'email' => $level >= 8 ? Str::lower("{$first}.{$family['surname']}{$index}@students.example.com") : null,
                'blood_group' => mt_rand(1, 10) <= 7 ? $this->pick(self::BLOOD_GROUPS) : null,
                'medical_notes' => mt_rand(1, 12) === 1 ? $this->pick($medical) : null,
                'address' => $family['address'],
                'parent_first_name' => $family['parent_first_name'],
                'parent_last_name' => $family['surname'],
                'parent_relationship' => $family['parent_relationship'],
                'parent_phone' => $family['parent_phone'],
                'parent_email' => $family['parent_email'],
                'parent_occupation' => $family['parent_occupation'],
                'parent_address' => $family['address'],
            ];
        }

        return $rows;
    }

    /**
     * Teaching staff for every subject plus the non-teaching team. The four drivers match
     * the seeded transport routes.
     *
     * @return list<array<string, string|int|null>>
     */
    private function staff(): array
    {
        $teaching = [
            ['English teacher', 'Languages', 'M.A. English', 4],
            ['Mathematics teacher', 'Mathematics', 'M.Sc. Mathematics', 4],
            ['Science teacher', 'Science', 'B.Sc. General Science, B.Ed.', 3],
            ['Social studies teacher', 'Humanities', 'M.A. History', 3],
            ['Computer science teacher', 'Technology', 'B.S. Computer Science', 2],
            ['Art teacher', 'Arts', 'B.F.A. Fine Arts', 2],
            ['Physical education teacher', 'Sports', 'B.S. Sports Science', 2],
            ['Physics teacher', 'Science', 'M.Sc. Physics', 2],
            ['Chemistry teacher', 'Science', 'M.Sc. Chemistry', 2],
            ['Biology teacher', 'Science', 'M.Sc. Biology', 2],
            ['Primary class teacher', 'Primary', 'B.Ed. Primary Education', 4],
        ];

        $support = [
            ['Vice principal', 'Administration', 'M.Ed. Educational Leadership', null],
            ['Accountant', 'Finance', 'B.Com., CPA', null],
            ['Assistant accountant', 'Finance', 'B.Com.', null],
            ['Librarian', 'Library', 'M.L.I.S.', null],
            ['Receptionist', 'Administration', 'Diploma in Office Administration', null],
            ['Admissions officer', 'Administration', 'B.A. Communications', null],
            ['IT administrator', 'Technology', 'B.S. Information Technology', null],
            ['Laboratory assistant', 'Science', 'Diploma in Laboratory Technology', null],
            ['Laboratory assistant', 'Science', 'Diploma in Laboratory Technology', null],
            ['School nurse', 'Health', 'B.S. Nursing', null],
            ['School counsellor', 'Student support', 'M.A. Counselling Psychology', null],
            ['Bus driver', 'Transport', 'Commercial driving licence', 'Daniel Brooks'],
            ['Bus driver', 'Transport', 'Commercial driving licence', 'Maria Lopez'],
            ['Bus driver', 'Transport', 'Commercial driving licence', 'Kwame Mensah'],
            ['Van driver', 'Transport', 'Commercial driving licence', 'Sofia Rossi'],
            ['Security officer', 'Facilities', 'Security training certificate', null],
            ['Security officer', 'Facilities', 'Security training certificate', null],
            ['Custodian', 'Facilities', null, null],
            ['Custodian', 'Facilities', null, null],
        ];

        $rows = [];
        $number = 0;

        foreach ($teaching as [$designation, $department, $qualification, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $rows[] = $this->staffRow(++$number, null, true, $designation, $department, $qualification);
            }
        }

        foreach ($support as [$designation, $department, $qualification, $name]) {
            $rows[] = $this->staffRow(++$number, $name, false, $designation, $department, $qualification);
        }

        return $rows;
    }

    /**
     * @return array<string, string|int|null>
     */
    private function staffRow(int $number, ?string $fullName, bool $teaching, string $designation, string $department, ?string $qualification): array
    {
        $isWoman = mt_rand(0, 1) === 1;
        [$first, $last] = $fullName !== null
            ? explode(' ', $fullName, 2)
            : [$this->pick($isWoman ? self::GIRLS : self::BOYS), $this->pick(self::SURNAMES)];

        if ($fullName !== null) {
            $isWoman = in_array($first, ['Maria', 'Sofia'], true);
        }

        $types = ['Full time', 'Full time', 'Full time', 'Full time', 'Part time', 'Contract'];

        return [
            'first_name' => $first,
            'last_name' => $last,
            'is_teaching' => $teaching ? 'Yes' : 'No',
            'designation' => $designation,
            'department' => $department,
            'employment_type' => $this->pick($types),
            'joined_on' => sprintf('%04d-%02d-%02d', mt_rand(2012, 2026), mt_rand(1, 8), mt_rand(1, 28)),
            'qualification' => $qualification,
            'gender' => $isWoman ? 'Female' : 'Male',
            'date_of_birth' => sprintf('%04d-%02d-%02d', mt_rand(1968, 2000), mt_rand(1, 12), mt_rand(1, 28)),
            'email' => Str::lower("{$first}.{$last}{$number}@greenfield.example.com"),
            'phone' => sprintf('+1 555 300 %04d', 1000 + $number),
            'national_id' => sprintf('ID%09d', 100000000 + $number * 7919),
            'bank_account' => sprintf('GFB-%010d', 2000000000 + $number * 104729),
            'address' => mt_rand(10, 480).' '.$this->pick(self::STREETS).', Springfield',
        ];
    }

    /**
     * Applications for the current year, mostly for the entry class.
     *
     * @return list<array<string, string|int|null>>
     */
    private function admissions(): array
    {
        $schools = ['Little Stars Kindergarten', 'Sunshine Montessori', 'Riverside Primary', 'Hillview Academy', null, null];
        $notes = ['Sibling already at Greenfield.', 'Moving to Springfield in January.', 'Interested in the school bus service.', null, null, null];
        $rows = [];

        foreach (range(1, 25) as $index) {
            $level = $index <= 12 ? 1 : mt_rand(2, 9);
            $isGirl = mt_rand(0, 1) === 1;
            $surname = $this->pick(self::SURNAMES);
            $guardianIsMother = mt_rand(0, 1) === 1;
            $guardian = $this->pick($guardianIsMother ? self::GIRLS : self::BOYS);

            $rows[] = [
                'class' => "Class {$level}",
                'academic_year' => null,
                'first_name' => $this->pick($isGirl ? self::GIRLS : self::BOYS),
                'last_name' => $surname,
                'gender' => $isGirl ? 'Female' : 'Male',
                'date_of_birth' => sprintf('%04d-%02d-%02d', 2026 - 5 - $level, mt_rand(1, 12), mt_rand(1, 28)),
                'previous_school' => $level === 1 ? $this->pick($schools) : 'Riverside Primary',
                'guardian_name' => "{$guardian} {$surname}",
                'guardian_relationship' => $guardianIsMother ? 'Mother' : 'Father',
                'guardian_phone' => sprintf('+1 555 400 %04d', 1000 + $index),
                'guardian_email' => Str::lower("{$guardian}.{$surname}.applicant{$index}@example.com"),
                'notes' => $this->pick($notes),
            ];
        }

        return $rows;
    }

    /**
     * 80 classics and reference titles plus 20 school workbooks. ISBNs are generated with a
     * valid check digit for the demo; they are not the real ISBNs of these editions.
     *
     * @return list<array<string, string|int|null>>
     */
    private function books(): array
    {
        $titles = [
            ['Pride and Prejudice', 'Jane Austen', 'Classics'], ['Emma', 'Jane Austen', 'Classics'], ['Sense and Sensibility', 'Jane Austen', 'Classics'],
            ['Jane Eyre', 'Charlotte Brontë', 'Classics'], ['Wuthering Heights', 'Emily Brontë', 'Classics'], ['Great Expectations', 'Charles Dickens', 'Classics'],
            ['Oliver Twist', 'Charles Dickens', 'Classics'], ['A Christmas Carol', 'Charles Dickens', 'Classics'], ['David Copperfield', 'Charles Dickens', 'Classics'],
            ['A Tale of Two Cities', 'Charles Dickens', 'Classics'], ['Little Women', 'Louisa May Alcott', 'Fiction'], ['The Adventures of Tom Sawyer', 'Mark Twain', 'Fiction'],
            ['Adventures of Huckleberry Finn', 'Mark Twain', 'Fiction'], ['Treasure Island', 'Robert Louis Stevenson', 'Fiction'], ['Kidnapped', 'Robert Louis Stevenson', 'Fiction'],
            ['Strange Case of Dr Jekyll and Mr Hyde', 'Robert Louis Stevenson', 'Classics'], ['Alice’s Adventures in Wonderland', 'Lewis Carroll', 'Children'], ['Through the Looking-Glass', 'Lewis Carroll', 'Children'],
            ['The Jungle Book', 'Rudyard Kipling', 'Children'], ['Kim', 'Rudyard Kipling', 'Fiction'], ['Robinson Crusoe', 'Daniel Defoe', 'Classics'],
            ['Gulliver’s Travels', 'Jonathan Swift', 'Classics'], ['Frankenstein', 'Mary Shelley', 'Classics'], ['Dracula', 'Bram Stoker', 'Classics'],
            ['The Picture of Dorian Gray', 'Oscar Wilde', 'Classics'], ['The Happy Prince and Other Tales', 'Oscar Wilde', 'Children'], ['Around the World in Eighty Days', 'Jules Verne', 'Fiction'],
            ['Twenty Thousand Leagues Under the Seas', 'Jules Verne', 'Fiction'], ['Journey to the Centre of the Earth', 'Jules Verne', 'Fiction'], ['The Time Machine', 'H. G. Wells', 'Science fiction'],
            ['The War of the Worlds', 'H. G. Wells', 'Science fiction'], ['The Invisible Man', 'H. G. Wells', 'Science fiction'], ['The Wind in the Willows', 'Kenneth Grahame', 'Children'],
            ['Peter Pan', 'J. M. Barrie', 'Children'], ['The Secret Garden', 'Frances Hodgson Burnett', 'Children'], ['A Little Princess', 'Frances Hodgson Burnett', 'Children'],
            ['Black Beauty', 'Anna Sewell', 'Children'], ['Heidi', 'Johanna Spyri', 'Children'], ['Anne of Green Gables', 'L. M. Montgomery', 'Fiction'],
            ['The Call of the Wild', 'Jack London', 'Fiction'], ['White Fang', 'Jack London', 'Fiction'], ['The Three Musketeers', 'Alexandre Dumas', 'Classics'],
            ['The Count of Monte Cristo', 'Alexandre Dumas', 'Classics'], ['Les Misérables', 'Victor Hugo', 'Classics'], ['Don Quixote', 'Miguel de Cervantes', 'Classics'],
            ['Moby-Dick', 'Herman Melville', 'Classics'], ['The Scarlet Letter', 'Nathaniel Hawthorne', 'Classics'], ['The Odyssey', 'Homer', 'Classics'],
            ['The Iliad', 'Homer', 'Classics'], ['Aesop’s Fables', 'Aesop', 'Children'], ['Grimms’ Fairy Tales', 'Jacob and Wilhelm Grimm', 'Children'],
            ['Andersen’s Fairy Tales', 'Hans Christian Andersen', 'Children'], ['The Wonderful Wizard of Oz', 'L. Frank Baum', 'Children'], ['The Hound of the Baskervilles', 'Arthur Conan Doyle', 'Mystery'],
            ['The Adventures of Sherlock Holmes', 'Arthur Conan Doyle', 'Mystery'], ['The Lost World', 'Arthur Conan Doyle', 'Fiction'], ['Hamlet', 'William Shakespeare', 'Drama'],
            ['Macbeth', 'William Shakespeare', 'Drama'], ['Romeo and Juliet', 'William Shakespeare', 'Drama'], ['A Midsummer Night’s Dream', 'William Shakespeare', 'Drama'],
            ['The Merchant of Venice', 'William Shakespeare', 'Drama'], ['Julius Caesar', 'William Shakespeare', 'Drama'], ['Leaves of Grass', 'Walt Whitman', 'Poetry'],
            ['Songs of Innocence and of Experience', 'William Blake', 'Poetry'], ['The Rime of the Ancient Mariner', 'Samuel Taylor Coleridge', 'Poetry'], ['Gitanjali', 'Rabindranath Tagore', 'Poetry'],
            ['The Prophet', 'Kahlil Gibran', 'Poetry'], ['On the Origin of Species', 'Charles Darwin', 'Science'], ['Relativity: The Special and General Theory', 'Albert Einstein', 'Science'],
            ['The Elements', 'Euclid', 'Mathematics'], ['Meditations', 'Marcus Aurelius', 'Philosophy'], ['The Republic', 'Plato', 'Philosophy'],
            ['The Art of War', 'Sun Tzu', 'History'], ['The Federalist Papers', 'Alexander Hamilton, James Madison and John Jay', 'History'], ['Narrative of the Life of Frederick Douglass', 'Frederick Douglass', 'History'],
            ['The Souls of Black Folk', 'W. E. B. Du Bois', 'History'], ['Up from Slavery', 'Booker T. Washington', 'History'], ['Walden', 'Henry David Thoreau', 'Philosophy'],
            ['The Autobiography of Benjamin Franklin', 'Benjamin Franklin', 'History'], ['Little Lord Fauntleroy', 'Frances Hodgson Burnett', 'Children'],
        ];

        foreach (range(1, 10) as $level) {
            $titles[] = ["Mathematics Workbook, Class {$level}", 'Greenfield Mathematics Department', 'Textbooks'];
        }

        foreach (range(6, 10) as $level) {
            $titles[] = ["Science Practical Notebook, Class {$level}", 'Greenfield Science Department', 'Textbooks'];
        }

        foreach (range(1, 5) as $level) {
            $titles[] = ["English Reader, Class {$level}", 'Greenfield Languages Department', 'Textbooks'];
        }

        $shelves = ['Classics' => 'C', 'Fiction' => 'F', 'Children' => 'J', 'Science fiction' => 'F', 'Mystery' => 'F', 'Drama' => 'D', 'Poetry' => 'P', 'Science' => 'S', 'Mathematics' => 'M', 'Philosophy' => 'H', 'History' => 'H', 'Textbooks' => 'T'];
        $rows = [];

        foreach ($titles as $index => [$title, $author, $category]) {
            $textbook = $category === 'Textbooks';

            $rows[] = [
                'title' => $title,
                'author' => $author,
                'isbn' => $this->isbn(13579 + $index * 211),
                'publisher' => $textbook ? 'Greenfield School Press' : $this->pick(['Penguin Classics', 'Oxford World’s Classics', 'Vintage Classics', 'Wordsworth Editions', 'Puffin Books']),
                'category' => $category,
                'shelf' => sprintf('%s-%02d', $shelves[$category], intdiv($index, 8) + 1),
                'copies' => $textbook ? 30 : mt_rand(1, 6),
            ];
        }

        return $rows;
    }

    /**
     * A 13-digit ISBN in the 979-8 range with a correct check digit.
     */
    private function isbn(int $seed): string
    {
        $body = '9798'.str_pad((string) (($seed * 7919) % 100000000), 8, '0', STR_PAD_LEFT);
        $sum = 0;

        foreach (str_split($body) as $position => $digit) {
            $sum += (int) $digit * ($position % 2 === 0 ? 1 : 3);
        }

        return $body.((10 - $sum % 10) % 10);
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }
}
