<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photos and ID-card verification tokens for students, guardians and staff; the school's
     * governing body (trustees, president, secretary …); and hostels with rooms and allocations.
     */
    public function up(): void
    {
        foreach (['students', 'guardians', 'employees'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('photo_path')->nullable();
                $table->string('card_token', 40)->nullable()->unique();
            });
        }

        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('position', 60);
            $table->string('body', 30);
            $table->string('email')->nullable();
            $table->text('phone')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('bio')->nullable();
            $table->date('term_starts_on')->nullable();
            $table->date('term_ends_on')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'body', 'sort_order']);
        });

        Schema::create('hostels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 20);
            $table->foreignId('warden_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->unsignedInteger('monthly_fee')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hostel_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30);
            $table->string('floor', 20)->nullable();
            $table->unsignedTinyInteger('capacity');
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['hostel_id', 'name']);
        });

        Schema::create('hostel_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('bed_label', 10)->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hostel_room_id', 'ends_on']);
            $table->index(['student_id', 'ends_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hostel_allocations');
        Schema::dropIfExists('hostel_rooms');
        Schema::dropIfExists('hostels');
        Schema::dropIfExists('organization_members');

        foreach (['students', 'guardians', 'employees'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique(["{$table->getTable()}_card_token_unique"]);
                $table->dropColumn(['photo_path', 'card_token']);
            });
        }
    }
};
