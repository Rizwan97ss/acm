<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online examinations: a reusable question bank, exams with their own copies of
 * questions, the students an exam is restricted to, attempts and answers.
 *
 * Answer keys are stored encrypted (text columns) so a database export never reveals
 * them; they are decrypted only for marking and for published results.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('difficulty', 10)->default('medium');
            $table->text('prompt');
            $table->json('options')->nullable();
            $table->text('answer_key')->nullable();
            $table->decimal('marks', 6, 2);
            $table->decimal('negative_marks', 6, 2)->default(0);
            $table->string('explanation', 2000)->nullable();
            $table->string('topic', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'subject_id', 'school_class_id'], 'bank_questions_subject_index');
        });

        Schema::create('online_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('instructions')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->decimal('total_marks', 7, 2);
            $table->decimal('passing_marks', 7, 2);
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->boolean('negative_marking')->default(false);
            $table->boolean('show_rank')->default(false);
            $table->string('status', 20)->default('draft');
            $table->string('results_status', 20)->default('pending');
            $table->foreignId('results_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('results_approved_at')->nullable();
            $table->foreignId('results_published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('results_published_at')->nullable();
            $table->dateTime('notified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'academic_year_id', 'school_class_id', 'status'], 'online_exams_audience_index');
        });

        Schema::create('online_exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('online_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_question_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->text('prompt');
            $table->json('options')->nullable();
            $table->text('answer_key')->nullable();
            $table->decimal('marks', 6, 2);
            $table->decimal('negative_marks', 6, 2)->default(0);
            $table->string('explanation', 2000)->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['online_exam_id', 'position']);
        });

        Schema::create('online_exam_student', function (Blueprint $table) {
            $table->foreignId('online_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            $table->primary(['online_exam_id', 'student_id']);
        });

        Schema::create('online_exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('online_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_no');
            $table->string('status', 20);
            $table->dateTime('started_at');
            $table->dateTime('deadline_at');
            $table->dateTime('submitted_at')->nullable();
            $table->string('submitted_by', 20)->nullable();
            $table->json('layout');
            $table->decimal('score', 7, 2)->nullable();
            $table->decimal('max_score', 7, 2);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->string('grade', 10)->nullable();
            $table->boolean('passed')->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('graded_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('last_saved_at')->nullable();
            $table->timestamps();

            $table->unique(['online_exam_id', 'student_id', 'attempt_no'], 'online_exam_attempts_number_unique');
            $table->index(['student_id', 'status']);
        });

        Schema::create('online_exam_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('online_exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('online_exam_question_id')->constrained()->cascadeOnDelete();
            $table->json('answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('marks_awarded', 6, 2)->nullable();
            $table->string('feedback', 2000)->nullable();
            $table->timestamps();

            $table->unique(['online_exam_attempt_id', 'online_exam_question_id'], 'online_exam_answers_attempt_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_exam_answers');
        Schema::dropIfExists('online_exam_attempts');
        Schema::dropIfExists('online_exam_student');
        Schema::dropIfExists('online_exam_questions');
        Schema::dropIfExists('online_exams');
        Schema::dropIfExists('bank_questions');
    }
};
