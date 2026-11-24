<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->json('params');
            $table->string('status');
            $table->string('file_path')->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
