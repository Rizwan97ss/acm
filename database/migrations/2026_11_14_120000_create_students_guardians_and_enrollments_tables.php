<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sensitive personal data (national IDs, medical notes, addresses, phones) is stored
     * encrypted in TEXT columns; `*_index` columns hold HMAC blind indexes for exact lookup.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('admission_no', 32);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('gender', 10);
            $table->date('date_of_birth');
            $table->string('email')->nullable();
            $table->text('national_id')->nullable();
            $table->string('national_id_index', 64)->nullable()->index();
            $table->string('blood_group', 3)->nullable();
            $table->text('medical_notes')->nullable();
            $table->text('address')->nullable();
            $table->date('admission_date');
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'admission_no']);
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'last_name', 'first_name']);
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email')->nullable();
            $table->text('phone')->nullable();
            $table->string('phone_index', 64)->nullable()->index();
            $table->string('occupation', 100)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'last_name', 'first_name']);
            $table->index(['school_id', 'email']);
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 16);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_emergency_contact')->default(true);

            $table->primary(['student_id', 'guardian_id']);
            $table->index('guardian_id');
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('roll_number')->nullable();
            $table->date('enrolled_on');
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id']);
            $table->unique(['section_id', 'academic_year_id', 'roll_number']);
            $table->index(['academic_year_id', 'section_id', 'status']);
            $table->index('school_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('students');
    }
};
