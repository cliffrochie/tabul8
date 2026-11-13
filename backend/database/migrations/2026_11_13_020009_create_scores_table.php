<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('criteria')->cascadeOnDelete();
            $table->foreignId('contestant_id')->constrained('contestants')->cascadeOnDelete();
            $table->foreignId('judge_id')->constrained('judges')->cascadeOnDelete();
            $table->decimal('raw_score', 6, 3);
            $table->decimal('weighted_score', 6, 3);
            $table->enum('status', ['DRAFT', 'LOCKED', 'UNLOCK_REQUESTED'])->default('DRAFT');
            $table->string('client_mutation_id', 64)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['round_id', 'criterion_id', 'contestant_id', 'judge_id'], 'scores_composite_unique');
            $table->index(['round_id', 'judge_id', 'contestant_id', 'status'], 'scores_sheet_status_idx');
            $table->index('client_mutation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};
