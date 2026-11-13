<?php

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a subject code is unique within a school but may repeat across schools', function () {
    $setup = academicSchool();
    Subject::factory()->create(['code' => 'MATH']);

    $this->actingAs($setup['admin'])->post(route('academics.subjects.store'), [
        'name' => 'Mathematics', 'code' => 'math', 'type' => 'core', 'is_active' => true,
    ])->assertSessionHasNoErrors();

    $this->actingAs($setup['admin'])->post(route('academics.subjects.store'), [
        'name' => 'Maths again', 'code' => 'MATH', 'type' => 'core', 'is_active' => true,
    ])->assertSessionHasErrors('code');

    $this->assertDatabaseHas('subjects', ['school_id' => $setup['school']->id, 'code' => 'MATH', 'name' => 'Mathematics']);
});

test('subjects scheduled in a timetable cannot be deleted', function () {
    $setup = academicSchool();
    TimetableEntry::factory()->create([
        'section_id' => $setup['section']->id, 'academic_year_id' => $setup['year']->id,
        'period_id' => $setup['period']->id, 'subject_id' => $setup['subject']->id,
    ]);

    $this->actingAs($setup['admin'])->delete(route('academics.subjects.destroy', $setup['subject']))->assertSessionHasErrors('subject');

    $this->assertModelExists($setup['subject']);
});

test('a class is created with its sections and subject allocation', function () {
    $setup = academicSchool();

    $response = $this->actingAs($setup['admin'])->post(route('academics.classes.store'), [
        'name' => 'Grade 7',
        'level' => 7,
        'sections' => [
            ['id' => null, 'name' => 'A', 'capacity' => 30, 'room' => '7A', 'class_teacher_id' => $setup['teacher']->id],
            ['id' => null, 'name' => 'B', 'capacity' => 28, 'room' => null, 'class_teacher_id' => null],
        ],
        'subjects' => [['id' => $setup['subject']->id, 'periods_per_week' => 6]],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('academics.classes.index'));
    $class = SchoolClass::query()->withoutGlobalScopes()->where('name', 'Grade 7')->sole();
    expect($class->school_id)->toBe($setup['school']->id)
        ->and($class->sections()->withoutGlobalScopes()->pluck('name')->all())->toBe(['A', 'B'])
        ->and($class->sections()->withoutGlobalScopes()->first()->class_teacher_id)->toBe($setup['teacher']->id)
        ->and($class->subjects()->first()->pivot->periods_per_week)->toBe(6);
});

test('class teachers must be teachers of the school', function () {
    $setup = academicSchool();
    $notATeacher = User::factory()->forSchool($setup['school'])->create();

    $this->actingAs($setup['admin'])->post(route('academics.classes.store'), [
        'name' => 'Grade 7',
        'level' => 7,
        'sections' => [['id' => null, 'name' => 'A', 'capacity' => 30, 'room' => null, 'class_teacher_id' => $notATeacher->id]],
        'subjects' => [],
    ])->assertSessionHasErrors('sections.0.class_teacher_id');

    $this->assertDatabaseMissing('school_classes', ['name' => 'Grade 7']);
});

test('subjects of another school cannot be allocated to a class', function () {
    $setup = academicSchool();
    $foreign = Subject::factory()->create();

    $this->actingAs($setup['admin'])->post(route('academics.classes.store'), [
        'name' => 'Grade 7',
        'level' => 7,
        'sections' => [],
        'subjects' => [['id' => $foreign->id, 'periods_per_week' => 3]],
    ])->assertSessionHasErrors('subjects.0.id');
});

test('sections and subjects used in a timetable cannot be removed from their class', function () {
    $setup = academicSchool();
    $class = $setup['section']->schoolClass;
    TimetableEntry::factory()->create([
        'section_id' => $setup['section']->id, 'academic_year_id' => $setup['year']->id,
        'period_id' => $setup['period']->id, 'subject_id' => $setup['subject']->id,
    ]);
    $base = ['name' => $class->name, 'level' => $class->level];

    $this->actingAs($setup['admin'])->put(route('academics.classes.update', $class), [
        ...$base,
        'sections' => [],
        'subjects' => [['id' => $setup['subject']->id, 'periods_per_week' => 5]],
    ])->assertSessionHasErrors('sections');

    $this->actingAs($setup['admin'])->put(route('academics.classes.update', $class), [
        ...$base,
        'sections' => [['id' => $setup['section']->id, 'name' => 'A', 'capacity' => 30, 'room' => null, 'class_teacher_id' => null]],
        'subjects' => [],
    ])->assertSessionHasErrors('subjects');

    $this->assertModelExists($setup['section']);
    expect($class->subjects()->count())->toBe(1);
});

test('the class list only shows the school\'s own classes', function () {
    $setup = academicSchool();
    SchoolClass::factory()->create(['name' => 'Foreign class']);

    $this->actingAs($setup['admin'])->get(route('academics.classes.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('academics/classes/index')
        ->has('classes', 1)
        ->where('classes.0.id', $setup['section']->school_class_id)
    );
});
