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
        Schema::create('library_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('author', 150)->nullable();
            $table->string('isbn', 20)->nullable();
            $table->string('publisher', 150)->nullable();
            $table->string('category', 60)->nullable();
            $table->string('shelf', 30)->nullable();
            $table->unsignedSmallInteger('copies_total');
            $table->unsignedSmallInteger('copies_available');
            $table->timestamps();

            $table->unique(['school_id', 'isbn']);
            $table->index(['school_id', 'title']);
        });

        Schema::create('library_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('library_book_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('issued_on');
            $table->date('due_on');
            $table->date('returned_on')->nullable();
            $table->unsignedBigInteger('fine')->default(0);
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'returned_on', 'due_on']);
            $table->index(['student_id', 'returned_on']);
            $table->index(['employee_id', 'returned_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('library_loans');
        Schema::dropIfExists('library_books');
    }
};
