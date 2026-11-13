<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('is_break')->default(false);
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->index(['school_id', 'position']);
        });

        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('room', 30)->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'section_id', 'day_of_week', 'period_id'], 'timetable_section_slot_unique');
            $table->index(['academic_year_id', 'teacher_id', 'day_of_week', 'period_id'], 'timetable_teacher_slot_index');
            $table->index(['academic_year_id', 'room', 'day_of_week', 'period_id'], 'timetable_room_slot_index');
            $table->index('school_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
        Schema::dropIfExists('periods');
    }
};
