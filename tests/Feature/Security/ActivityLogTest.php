<?php

use App\Http\Middleware\LogActivity;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;

test('controller actions are logged with the user, action and bound record', function () {
    $path = captureLogChannel(LogActivity::CHANNEL);
    $user = userWithPermissions(['students.view'], $school = School::factory()->create());
    $student = Student::factory()->create(['school_id' => $school->id]);

    $this->actingAs($user)->get(route('people.students.show', $student))->assertOk();

    expect(File::get($path))
        ->toContain('INFO: GET people.students.show')
        ->toContain('"user_id":'.$user->id)
        ->toContain('StudentController@show')
        ->toContain('"parameters":{"student":'.$student->id.'}')
        ->toContain('"status":200');
});

test('denied actions are logged as warnings', function () {
    $path = captureLogChannel(LogActivity::CHANNEL);
    $user = User::factory()->forSchool(School::factory()->create())->create();

    $this->actingAs($user)->get(route('people.students.index'))->assertForbidden();

    expect(File::get($path))->toContain('WARNING: GET people.students.index')->toContain('"status":403');
});

test('submitted values are never logged, only field names', function () {
    $path = captureLogChannel(LogActivity::CHANNEL);
    $user = User::factory()->forSchool(School::factory()->create())->create();

    $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'Brand-New-Secret-77',
        'password_confirmation' => 'Brand-New-Secret-77',
    ]);

    expect(File::get($path))
        ->toContain('PUT user-password.update')
        ->toContain('"fields":["current_password","password","password_confirmation"]')
        ->not->toContain('Brand-New-Secret-77');
});

test('API requests are logged too', function () {
    $path = captureLogChannel(LogActivity::CHANNEL);
    $user = User::factory()->forSchool(School::factory()->create())->create();
    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('api.v1.me'))->assertOk();

    expect(File::get($path))->toContain('GET api.v1.me')->toContain('"user_id":'.$user->id);
});
