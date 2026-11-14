<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One table for all staff; `is_teaching` separates teachers (teachers.* permissions)
     * from other employees (employees.* permissions).
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('employee_no', 32);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('email')->nullable();
            $table->text('phone')->nullable();
            $table->string('phone_index', 64)->nullable()->index();
            $table->text('national_id')->nullable();
            $table->string('national_id_index', 64)->nullable()->index();
            $table->text('bank_account')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_teaching')->default(false);
            $table->string('designation', 100);
            $table->string('department', 100)->nullable();
            $table->string('qualification', 150)->nullable();
            $table->string('employment_type', 16)->default('full_time');
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'employee_no']);
            $table->index(['school_id', 'is_teaching', 'status']);
            $table->index(['school_id', 'last_name', 'first_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
