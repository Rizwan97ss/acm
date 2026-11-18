<?php

use App\Http\Controllers\Learning\CourseController;
use App\Http\Controllers\Learning\CourseOutlineController;
use App\Http\Controllers\Learning\LearnController;
use App\Http\Controllers\Learning\LearningDashboardController;
use App\Http\Controllers\Learning\LearningFileController;
use App\Http\Controllers\Learning\LiveClassController;
use App\Http\Controllers\Learning\QuizController;
use App\Http\Controllers\Learning\QuizTakeController;
use App\Http\Middleware\EnsureLearningEnabled;
use App\Http\Middleware\RequireSchoolContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', RequireSchoolContext::class, EnsureLearningEnabled::class])->group(function () {
    /*
    | Teachers and administrators
    */
    Route::prefix('learning')->name('learning.')->group(function () {
        Route::get('/', LearningDashboardController::class)->name('dashboard');
        Route::get('roster', [LearningFileController::class, 'roster'])->name('roster');

        Route::resource('courses', CourseController::class);
        Route::post('courses/{course}/chapters', [CourseOutlineController::class, 'storeChapter'])->name('courses.chapters.store');
        Route::put('chapters/{chapter}', [CourseOutlineController::class, 'updateChapter'])->name('chapters.update');
        Route::delete('chapters/{chapter}', [CourseOutlineController::class, 'destroyChapter'])->name('chapters.destroy');
        Route::post('chapters/{chapter}/move', [CourseOutlineController::class, 'moveChapter'])->name('chapters.move');

        Route::post('chapters/{chapter}/lessons', [CourseOutlineController::class, 'storeLesson'])->name('chapters.lessons.store');
        Route::get('lessons/{lesson}/edit', [CourseOutlineController::class, 'editLesson'])->name('lessons.edit');
        Route::put('lessons/{lesson}', [CourseOutlineController::class, 'updateLesson'])->name('lessons.update');
        Route::delete('lessons/{lesson}', [CourseOutlineController::class, 'destroyLesson'])->name('lessons.destroy');
        Route::post('lessons/{lesson}/move', [CourseOutlineController::class, 'moveLesson'])->name('lessons.move');

        Route::post('lessons/{lesson}/materials', [CourseOutlineController::class, 'storeMaterial'])->middleware('throttle:60,1')->name('lessons.materials.store');
        Route::put('materials/{material}', [CourseOutlineController::class, 'updateMaterial'])->name('materials.update');
        Route::delete('materials/{material}', [CourseOutlineController::class, 'destroyMaterial'])->name('materials.destroy');
        Route::post('materials/{material}/move', [CourseOutlineController::class, 'moveMaterial'])->name('materials.move');

        Route::resource('live-classes', LiveClassController::class)->except('show');
        Route::post('live-classes/{live_class}/cancel', [LiveClassController::class, 'cancel'])->name('live-classes.cancel');

        Route::resource('quizzes', QuizController::class);
        Route::post('quizzes/{quiz}/questions', [QuizController::class, 'storeQuestion'])->name('quizzes.questions.store');
        Route::put('questions/{question}', [QuizController::class, 'updateQuestion'])->name('questions.update');
        Route::delete('questions/{question}', [QuizController::class, 'destroyQuestion'])->name('questions.destroy');
        Route::post('questions/{question}/move', [QuizController::class, 'moveQuestion'])->name('questions.move');
        Route::get('quizzes/{quiz}/attempts/{attempt}', [QuizController::class, 'attempt'])->name('quizzes.attempts.show');
        Route::put('quizzes/{quiz}/attempts/{attempt}', [QuizController::class, 'grade'])->name('quizzes.attempts.grade');

        Route::get('files/materials/{material}', [LearningFileController::class, 'material'])->middleware('signed')->name('files.material');
        Route::get('files/assignments/{file}', [LearningFileController::class, 'assignment'])->middleware('signed')->name('files.assignment');
    });

    /*
    | Students (and parents, read-only)
    */
    Route::prefix('learn')->name('learn.')->group(function () {
        Route::get('/', [LearnController::class, 'index'])->name('index');
        Route::get('courses/{course}', [LearnController::class, 'course'])->name('courses.show');
        Route::get('lessons/{lesson}', [LearnController::class, 'lesson'])->name('lessons.show');
        Route::post('lessons/{lesson}/complete', [LearnController::class, 'complete'])->name('lessons.complete');
        Route::post('lessons/{lesson}/bookmark', [LearnController::class, 'bookmark'])->name('lessons.bookmark');
        Route::get('live-classes/{live_class}/join', [LearnController::class, 'join'])->middleware('throttle:30,1')->name('live-classes.join');

        Route::get('quizzes/{quiz}', [QuizTakeController::class, 'show'])->name('quizzes.show');
        Route::post('quizzes/{quiz}/start', [QuizTakeController::class, 'start'])->middleware('throttle:20,1')->name('quizzes.start');
        Route::post('quizzes/{quiz}/attempts/{attempt}', [QuizTakeController::class, 'submit'])->middleware('throttle:20,1')->name('quizzes.submit');
    });
});
