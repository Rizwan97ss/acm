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
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->string('type', 16)->default('core');
            $table->string('color', 7)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code']);
            $table->index(['school_id', 'name']);
        });

        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->unsignedSmallInteger('level')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'level']);
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('room', 30)->nullable();
            $table->foreignId('class_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_class_id', 'name']);
            $table->index('school_id');
        });

        Schema::create('class_subject', function (Blueprint $table) {
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('periods_per_week')->default(0);

            $table->primary(['school_class_id', 'subject_id']);
            $table->index('subject_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_subject');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('subjects');
    }
};
