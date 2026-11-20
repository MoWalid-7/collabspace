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
            $table->string('avatar')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('job_title')->nullable();
            $table->unsignedSmallInteger('years_of_experience')->nullable();
            $table->text('about')->nullable();
            $table->string('availability_status')->default('available');
            $table->string('google_id')->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn([
                'avatar',
                'phone',
                'job_title',
                'years_of_experience',
                'about',
                'availability_status',
                'google_id',
            ]);
        });
    }
};
