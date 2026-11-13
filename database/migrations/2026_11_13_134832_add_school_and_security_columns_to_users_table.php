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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->text('phone')->nullable()->after('email');
            $table->string('phone_index', 64)->nullable()->after('phone')->index();
            $table->boolean('is_active')->default(true)->after('password');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            $table->index(['school_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'is_active']);
            $table->dropConstrainedForeignId('school_id');
            $table->dropIndex(['phone_index']);
            $table->dropColumn(['phone', 'phone_index', 'is_active', 'last_login_at', 'last_login_ip']);
        });
    }
};
