<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Settings are stored per scope: global (scope_id 0), school (scope_id = school id)
     * and user (scope_id = user id). Resolution falls back user -> school -> global -> default.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 16);
            $table->unsignedBigInteger('scope_id')->default(0);
            $table->string('key', 100);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'scope_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
