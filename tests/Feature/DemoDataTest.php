<?php

use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\LibraryLoan;
use App\Models\Payment;
use App\Models\Payslip;
use App\Models\School;
use App\Models\Student;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\Import\ImportBatch;
use App\Services\Import\ImportRegistry;
use App\Services\Import\ImportService;
use App\Services\Tenancy\CurrentSchool;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoActivitySeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

test('the demo spreadsheets import cleanly and the activity seeder fills every module', function () {
    Notification::fake();
    Storage::fake('local');
    $directory = storage_path('framework/testing/demo-imports');

    $this->seed(DatabaseSeeder::class);
    $this->artisan('demo:spreadsheets', ['--path' => $directory])->assertSuccessful();

    $school = School::query()->where('code', 'GHS')->firstOrFail();
    $admin = User::query()->where('email', 'school.admin@example.com')->firstOrFail();
    app(CurrentSchool::class)->set($school);
    $this->actingAs($admin);

    foreach (['students' => '1-students-and-parents.xlsx', 'staff' => '2-staff.xlsx', 'admissions' => '3-admission-applications.xlsx', 'library-books' => '4-library-books.xlsx'] as $key => $file) {
        $imported = app(ImportService::class)->import(
            app(ImportRegistry::class)->find($key),
            "{$directory}/{$file}",
            'xlsx',
            new ImportBatch($admin, $school->id),
            $file,
        );

        expect($imported)->toBeGreaterThan(20);
    }

    $this->seed(DemoActivitySeeder::class);

    expect(Student::query()->count())->toBe(101)
        ->and(TimetableEntry::query()->count())->toBeGreaterThan(400)
        ->and(AttendanceRecord::query()->exists())->toBeTrue()
        ->and(Invoice::query()->count())->toBe(400)
        ->and(Payment::query()->exists())->toBeTrue()
        ->and(Payslip::query()->exists())->toBeTrue()
        ->and(LibraryLoan::query()->exists())->toBeTrue();

    File::deleteDirectory($directory);
});
