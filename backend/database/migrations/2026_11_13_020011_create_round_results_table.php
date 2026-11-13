<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->foreignId('contestant_id')->constrained('contestants')->cascadeOnDelete();
            $table->decimal('mean_score', 6, 3)->default(0.000);
            $table->decimal('trimmed_mean', 6, 3)->default(0.000);
            $table->decimal('rank_sum', 5, 2)->default(0.00);
            $table->unsignedInteger('first_place_count')->default(0);
            $table->decimal('deduction_points', 5, 2)->default(0.00);
            $table->decimal('net_composite', 6, 3)->default(0.000);
            $table->decimal('cumulative_score', 6, 3)->nullable();
            $table->decimal('primary_crit_avg', 6, 3)->default(0.000);
            $table->decimal('chairman_rank', 5, 2)->nullable();
            $table->unsignedInteger('final_rank');
            $table->boolean('is_tied')->default(false);
            $table->string('tie_break_rule_applied', 64)->nullable();
            $table->boolean('is_qualified')->default(false);
            $table->json('judge_breakdown_snapshot')->default('[]');
            $table->timestamp('computed_at');

            $table->unique(['round_id', 'contestant_id']);
            $table->index(['round_id', 'final_rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_results');
    }
};
