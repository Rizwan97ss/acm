<?php

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Points the activity channel at a temporary directory and writes one day's log lines.
 *
 * @param  list<array{school: int|null, user: int|null, route: string, status: int, level?: string}>  $lines
 */
function activityLogFixture(string $date, array $lines): void
{
    $directory = storage_path('framework/testing/activity-'.Str::uuid());
    File::ensureDirectoryExists($directory);
    config(['logging.channels.activity.path' => $directory.'/activity.log']);
    test()->beforeApplicationDestroyed(fn () => File::deleteDirectory($directory));

    $content = '';

    foreach ($lines as $index => $line) {
        $context = json_encode(['user_id' => $line['user'], 'action' => 'People\StudentController@index', 'parameters' => [], 'fields' => [], 'query' => [], 'status' => $line['status'], 'duration_ms' => 12, 'ip' => '127.0.0.1']);
        $extra = $line['school'] === null ? '[]' : json_encode(['school_id' => $line['school']]);
        $content .= sprintf("[%s 10:00:%02d] local.%s: GET %s %s %s\n", $date, $index, strtoupper($line['level'] ?? 'info'), $line['route'], $context, $extra);
    }

    File::put($directory."/activity-{$date}.log", $content);
}

test('administrators see the day\'s activity of their own school only, newest first', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(['audit_logs.view'], $school);
    activityLogFixture('2026-11-10', [
        ['school' => $school->id, 'user' => $admin->id, 'route' => 'people.students.index', 'status' => 200],
        ['school' => School::factory()->create()->id, 'user' => null, 'route' => 'other.school.page', 'status' => 200],
        ['school' => $school->id, 'user' => $admin->id, 'route' => 'finance.invoices.index', 'status' => 403, 'level' => 'warning'],
    ]);

    $this->actingAs($admin)->get(route('admin.activity-logs.index', ['date' => '2026-11-10']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/activity-logs')
            ->has('entries', 2)
            ->where('entries.0.route', 'finance.invoices.index')
            ->where('entries.0.user.name', $admin->name)
            ->where('entries.1.route', 'people.students.index')
            ->where('dates', ['2026-11-10']));
});

test('entries can be filtered by result', function () {
    $school = School::factory()->create();
    $admin = userWithPermissions(['audit_logs.view'], $school);
    activityLogFixture('2026-11-10', [
        ['school' => $school->id, 'user' => $admin->id, 'route' => 'people.students.index', 'status' => 200],
        ['school' => $school->id, 'user' => $admin->id, 'route' => 'finance.invoices.index', 'status' => 403, 'level' => 'warning'],
    ]);

    $this->actingAs($admin)->get(route('admin.activity-logs.index', ['date' => '2026-11-10', 'level' => 'warning']))
        ->assertInertia(fn (Assert $page) => $page->has('entries', 1)->where('entries.0.status', 403));
});

test('the activity log needs the audit log permission and a valid date', function () {
    $school = School::factory()->create();

    $this->actingAs(User::factory()->forSchool($school)->create())->get(route('admin.activity-logs.index'))->assertForbidden();
    $this->actingAs(userWithPermissions(['audit_logs.view'], $school))->get(route('admin.activity-logs.index', ['date' => '../../.env']))->assertSessionHasErrors('date');
});
