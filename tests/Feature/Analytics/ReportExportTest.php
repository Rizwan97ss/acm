<?php

use App\Models\AuditLog;
use App\Models\Expense;
use App\Services\Reports\CsvExporter;
use Inertia\Testing\AssertableInertia as Assert;

test('the reports page lists only exports the user may download', function () {
    $setup = teachingSchool();

    $this->actingAs(userWithPermissions(['reports.export', 'students.view'], $setup['school']))
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('analytics/reports')
            ->has('reports', 1)
            ->where('reports.0.key', 'students'));
});

test('exports require reports.export and the module permission', function (array $permissions) {
    $setup = teachingSchool();

    $this->actingAs(userWithPermissions($permissions, $setup['school']))
        ->get(route('reports.export', 'students'))
        ->assertForbidden();
})->with([
    'no reports.export' => [['students.view']],
    'no module permission' => [['reports.export']],
]);

test('student export neutralises spreadsheet formulas and is audited', function () {
    $setup = teachingSchool();
    $setup['student']->update(['first_name' => '=HYPERLINK("http://evil.test")']);
    $user = userWithPermissions(['reports.export', 'students.view'], $setup['school']);

    $response = $this->actingAs($user)->get(route('reports.export', 'students'));

    $response->assertOk()->assertDownload();
    $csv = $response->streamedContent();

    expect($csv)
        ->toContain($setup['student']->admission_no)
        ->toContain('"\'=HYPERLINK(""http://evil.test"")"')
        ->not->toContain(',=HYPERLINK');
    expect(AuditLog::query()->where('event', 'report.exported')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('exports are scoped to the current school and date range', function () {
    $setup = teachingSchool();
    $other = teachingSchool();
    Expense::factory()->create(['school_id' => $setup['school']->id, 'payee' => 'In range', 'spent_on' => now()->toDateString()]);
    Expense::factory()->create(['school_id' => $setup['school']->id, 'payee' => 'Too old', 'spent_on' => now()->subMonths(3)->toDateString()]);
    Expense::factory()->create(['school_id' => $other['school']->id, 'payee' => 'Other school', 'spent_on' => now()->toDateString()]);

    $csv = $this->actingAs(userWithPermissions(['reports.export', 'expenses.view'], $setup['school']))
        ->get(route('reports.export', ['report' => 'expenses', 'from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()]))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('In range')->not->toContain('Too old')->not->toContain('Other school');
});

test('exports are limited to one year and unknown reports are not found', function () {
    $setup = teachingSchool();
    $user = userWithPermissions(['reports.export', 'expenses.view'], $setup['school']);

    $this->actingAs($user)
        ->get(route('reports.export', ['report' => 'expenses', 'from' => now()->subYears(2)->toDateString(), 'to' => now()->toDateString()]))
        ->assertSessionHasErrors('from');

    $this->actingAs($user)->get(route('reports.export', 'salaries'))->assertNotFound();
});

test('csv cells starting with formula characters are prefixed but numbers are not', function (mixed $value, string $expected) {
    expect(app(CsvExporter::class)->cell($value))->toBe($expected);
})->with([
    ['=1+1', "'=1+1"],
    ['+cmd', "'+cmd"],
    ['@SUM(A1)', "'@SUM(A1)"],
    ['-12.50', '-12.50'],
    ['Plain', 'Plain'],
    [null, ''],
    [true, 'Yes'],
]);
