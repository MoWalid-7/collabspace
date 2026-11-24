<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('join_url', 1000)->nullable();
            $table->string('status')->default('scheduled');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('starts_at');
            $table->index('project_id');
        });

        Schema::create('meeting_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('response')->default('pending');
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
            $table->index(['user_id', 'meeting_id']);
        });

        Schema::create('meeting_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('meeting_id');
        });

        Schema::create('daily_statuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('done_yesterday');
            $table->text('plan_today');
            $table->text('blockers')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'date']);
            $table->index(['workspace_id', 'user_id', 'date']);
            $table->index(['project_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_statuses');
        Schema::dropIfExists('meeting_notes');
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
    }
};
