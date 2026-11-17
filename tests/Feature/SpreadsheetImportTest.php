<?php

use App\Enums\AdmissionStatus;
use App\Models\AdmissionApplication;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\LibraryBook;
use App\Models\Section;
use App\Models\Student;
use App\Services\Import\ImportRegistry;
use App\Services\Import\SpreadsheetReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

/**
 * An uploaded .xlsx whose first row is the header.
 *
 * @param  list<list<string|int|null>>  $rows
 */
function xlsxUpload(array $rows, string $name = 'import.xlsx'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx-test-').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

/**
 * The pending preview stored for an importer.
 *
 * @return array<string, mixed>
 */
function pendingImport(string $type): array
{
    return session("imports.{$type}");
}

beforeEach(function () {
    Storage::fake('local');
});

test('the template is an Excel file whose header row the importer accepts', function () {
    $setup = academicSchool();
    $user = userWithPermissions(['students.create', 'guardians.create'], $setup['school']);

    $response = $this->actingAs($user)->get(route('imports.template', 'students'))->assertOk();
    $file = $response->baseResponse->getFile()->getPathname();

    $header = null;
    $reader = new Reader;
    $reader->open($file);

    foreach ($reader->getSheetIterator() as $sheet) {
        expect($sheet->getName())->toBe('Data');

        foreach ($sheet->getRowIterator() as $row) {
            $header = $row->toArray();

            break;
        }

        break;
    }

    $reader->close();

    expect($header)->toContain('First name *', 'Date of birth *', 'Parent phone')
        ->and(collect($header)->map(fn (string $heading) => SpreadsheetReader::headingKey($heading))->all())
        ->toContain('first_name', 'parent_phone', 'class');
});

test('students and their parents are imported, enrolled, and siblings share one parent', function () {
    $setup = academicSchool();
    $user = userWithPermissions(['students.create', 'guardians.create'], $setup['school']);
    $className = $setup['section']->schoolClass->name;

    $file = xlsxUpload([
        ['First name *', 'Last name *', 'Gender *', 'Date of birth *', 'Class', 'Section', 'Blood group', 'Parent first name', 'Parent last name', 'Parent relationship', 'Parent phone'],
        ['Aisha', 'Khan', 'Female', '21/06/2015', strtoupper($className), 'a', 'b+', 'Imran', 'Khan', 'Father', '0300 1234567'],
        ['Omar', 'Khan', 'male', '2017-02-03', $className, 'A', null, 'Imran', 'Khan', 'father', '0300 1234567'],
    ]);

    $this->actingAs($user)
        ->post(route('imports.preview', 'students'), ['file' => $file])
        ->assertRedirect(route('imports.show', 'students'));

    $pending = pendingImport('students');
    expect($pending['report'])->toMatchArray(['total' => 2, 'valid' => 2, 'invalid' => 0])
        ->and(Student::query()->count())->toBe(0);

    $this->actingAs($user)
        ->post(route('imports.store', 'students'), ['token' => $pending['token']])
        ->assertRedirect(route('imports.show', 'students'))
        ->assertSessionHasNoErrors();

    $students = Student::query()->orderBy('first_name')->get();

    expect($students)->toHaveCount(2)
        ->and($students->pluck('admission_no')->filter()->count())->toBe(2)
        ->and($students[0]->date_of_birth->toDateString())->toBe('2015-06-21')
        ->and($students[0]->blood_group)->toBe('B+')
        ->and(Guardian::query()->count())->toBe(1)
        ->and(Guardian::query()->sole()->students()->count())->toBe(2)
        ->and(Enrollment::query()->where('section_id', $setup['section']->id)->count())->toBe(2)
        ->and(AuditLog::query()->where('event', 'import.students')->exists())->toBeTrue()
        ->and(session('imports.students'))->toBeNull();

    Storage::disk('local')->assertMissing($pending['path']);
});

test('problems are reported per row and nothing is saved', function () {
    $setup = academicSchool();
    $user = userWithPermissions(['students.create', 'guardians.create'], $setup['school']);
    Student::factory()->create(['school_id' => $setup['school']->id, 'admission_no' => 'TAKEN1']);

    $file = xlsxUpload([
        ['admission_no', 'first_name', 'last_name', 'gender', 'date_of_birth', 'class', 'section'],
        ['NEW1', 'Valid', 'Row', 'female', '2015-01-01', null, null],
        ['TAKEN1', 'Taken', 'Number', 'male', '2015-01-01', null, null],
        ['NEW1', 'Repeated', 'Number', 'robot', '2015-01-01', null, null],
        ['NEW2', 'Unknown', 'Class', 'male', '2015-01-01', 'Class 99', 'A'],
    ]);

    $this->actingAs($user)->post(route('imports.preview', 'students'), ['file' => $file]);

    $report = pendingImport('students')['report'];
    $errors = collect($report['errors'])->keyBy('line');

    expect($report)->toMatchArray(['total' => 4, 'valid' => 1, 'invalid' => 3])
        ->and($errors->keys()->all())->toBe([3, 4, 5])
        ->and(implode(' ', $errors[3]['messages']))->toContain('Admission no')
        ->and(implode(' ', $errors[4]['messages']))->toContain('Gender')
        ->and(implode(' ', $errors[5]['messages']))->toContain('Class 99');

    $this->actingAs($user)
        ->post(route('imports.store', 'students'), ['token' => pendingImport('students')['token']])
        ->assertSessionHasErrors('file');

    expect(Student::query()->count())->toBe(1);
});

test('a row that fails while saving rolls back the whole file', function () {
    $setup = academicSchool();
    $user = userWithPermissions(['students.create'], $setup['school']);
    $setup['section']->update(['capacity' => 1]);
    $className = $setup['section']->schoolClass->name;

    $file = xlsxUpload([
        ['first_name', 'last_name', 'gender', 'date_of_birth', 'class', 'section'],
        ['First', 'Seat', 'female', '2015-01-01', $className, 'A'],
        ['Second', 'Seat', 'male', '2015-01-01', $className, 'A'],
    ]);

    $this->actingAs($user)->post(route('imports.preview', 'students'), ['file' => $file]);

    $this->actingAs($user)
        ->post(route('imports.store', 'students'), ['token' => pendingImport('students')['token']])
        ->assertInvalid(['file' => 'Row 3: Section A is full']);

    expect(Student::query()->count())->toBe(0)
        ->and(Enrollment::query()->count())->toBe(0);
});

test('staff are imported with generated numbers, and teachers-only users cannot add other staff', function () {
    $setup = academicSchool();
    $header = ['first_name', 'last_name', 'is_teaching', 'designation', 'employment_type', 'joined_on'];

    $hr = userWithPermissions(['employees.create'], $setup['school']);
    $this->actingAs($hr)->post(route('imports.preview', 'staff'), ['file' => xlsxUpload([
        $header,
        ['Sara', 'Ahmed', 'Yes', 'Maths teacher', 'Full time', '2024-08-01'],
        ['Kamal', 'Shah', 'No', 'Accountant', 'contract', '01/09/2024'],
    ])]);
    $this->actingAs($hr)->post(route('imports.store', 'staff'), ['token' => pendingImport('staff')['token']])->assertSessionHasNoErrors();

    expect(Employee::query()->count())->toBe(2)
        ->and(Employee::query()->where('is_teaching', false)->sole()->employment_type->value)->toBe('contract')
        ->and(Employee::query()->whereNull('employee_no')->exists())->toBeFalse();

    $headTeacher = userWithPermissions(['teachers.create'], $setup['school']);
    $this->actingAs($headTeacher)->post(route('imports.preview', 'staff'), ['file' => xlsxUpload([
        $header,
        ['Nadia', 'Ali', 'No', 'Clerk', 'full_time', '2024-08-01'],
    ])]);

    expect(pendingImport('staff')['report']['errors'][0]['messages'])->toContain('You can only import teaching staff.');
});

test('admission applications are added to the pending queue', function () {
    $setup = academicSchool();
    $user = userWithPermissions(['admissions.create'], $setup['school']);

    $this->actingAs($user)->post(route('imports.preview', 'admissions'), ['file' => xlsxUpload([
        ['Class applied for', 'First name', 'Last name', 'Gender', 'Date of birth', 'Guardian name', 'Guardian relationship', 'Guardian phone'],
        [$setup['section']->schoolClass->name, 'Bilal', 'Raza', 'Male', '2019-11-02', 'Usman Raza', 'Father', '+92 321 5550101'],
    ])]);
    $this->actingAs($user)->post(route('imports.store', 'admissions'), ['token' => pendingImport('admissions')['token']])->assertSessionHasNoErrors();

    $application = AdmissionApplication::query()->sole();

    expect($application->status)->toBe(AdmissionStatus::Pending)
        ->and($application->academic_year_id)->toBe($setup['year']->id)
        ->and($application->guardian_phone)->toBe('+92 321 5550101');
});

test('books are imported with ISBNs normalised and duplicates in the file flagged', function () {
    $setup = academicSchool();
    $user = userWithPermissions(['library.view', 'library.create'], $setup['school']);

    $this->actingAs($user)->post(route('imports.preview', 'library-books'), ['file' => xlsxUpload([
        ['Title', 'ISBN', 'Copies'],
        ['Brief History', '978-0553380163', 3],
        ['Same book again', '9780553380163', 1],
    ])]);

    expect(pendingImport('library-books')['report']['errors'][0])->toMatchArray(['line' => 3]);

    $this->actingAs($user)->post(route('imports.preview', 'library-books'), ['file' => xlsxUpload([
        ['Title', 'ISBN', 'Copies'],
        ['Brief History', '978-0553380163', 3],
    ])]);
    $this->actingAs($user)->post(route('imports.store', 'library-books'), ['token' => pendingImport('library-books')['token']])->assertSessionHasNoErrors();

    expect(LibraryBook::query()->sole())->isbn->toBe('9780553380163')->copies_available->toBe(3);
});

test('csv files work and a missing required column is explained', function () {
    $setup = academicSchool();
    $user = userWithPermissions(['library.view', 'library.create'], $setup['school']);

    $this->actingAs($user)->post(route('imports.preview', 'library-books'), [
        'file' => UploadedFile::fake()->createWithContent('books.csv', "Title,Copies\nAtlas,2\n"),
    ])->assertSessionHasNoErrors();

    expect(pendingImport('library-books')['report']['valid'])->toBe(1);

    $this->actingAs($user)->post(route('imports.preview', 'library-books'), [
        'file' => UploadedFile::fake()->createWithContent('books.csv', "Title,Author\nAtlas,Someone\n"),
    ])->assertInvalid(['file' => 'Required columns are missing: Copies']);
});

test('imports are limited to what the user may create', function () {
    $setup = academicSchool();
    $librarian = userWithPermissions(['library.view', 'library.create'], $setup['school']);

    $this->actingAs($librarian)->get(route('imports.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('imports/index')
            ->where('importers', fn ($importers) => collect($importers)->pluck('key')->all() === ['library-books']));

    $this->actingAs($librarian)->get(route('imports.show', 'students'))->assertForbidden();
    $this->actingAs($librarian)->post(route('imports.preview', 'students'), ['file' => xlsxUpload([['first_name']])])->assertForbidden();
    $this->actingAs($librarian)->get(route('imports.show', 'unknown'))->assertNotFound();

    expect(count(ImportRegistry::IMPORTERS))->toBe(4);
});

test('an import needs the token from this user’s own preview', function () {
    $setup = academicSchool();
    $user = userWithPermissions(['library.view', 'library.create'], $setup['school']);

    $this->actingAs($user)->post(route('imports.preview', 'library-books'), ['file' => xlsxUpload([
        ['Title', 'Copies'],
        ['Atlas', 2],
    ])]);

    $this->actingAs($user)
        ->post(route('imports.store', 'library-books'), ['token' => 'forged'])
        ->assertSessionHasErrors('file');

    expect(LibraryBook::query()->count())->toBe(0);
});

test('sections in another school are never matched', function () {
    $setup = academicSchool();
    $other = academicSchool();
    $user = userWithPermissions(['students.create'], $setup['school']);
    $foreign = Section::query()->withoutGlobalScopes()->findOrFail($other['section']->id);
    $foreign->schoolClass()->withoutGlobalScopes()->first()->update(['name' => 'Foreign class']);

    $this->actingAs($user)->post(route('imports.preview', 'students'), ['file' => xlsxUpload([
        ['first_name', 'last_name', 'gender', 'date_of_birth', 'class', 'section'],
        ['Ali', 'Raza', 'male', '2015-01-01', 'Foreign class', 'A'],
    ])]);

    expect(pendingImport('students')['report']['invalid'])->toBe(1);
});
