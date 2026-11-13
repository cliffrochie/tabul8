<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->foreignId('score_id')->nullable()->constrained('scores')->nullOnDelete();
            $table->foreignId('judge_id')->nullable()->constrained('judges')->nullOnDelete();
            $table->foreignId('contestant_id')->nullable()->constrained('contestants')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('action', [
                'LOCKED',
                'UNLOCK_REQ',
                'UNLOCK_APPROVED',
                'UNLOCK_REJECTED',
                'SCORE_EDITED',
                'DEDUCTION_APPLIED',
                'ROUND_FROZEN',
                'ROUND_UNFROZEN',
                'STRATEGY_CHANGED',
                'JUDGE_EXCLUDED',
            ]);
            $table->decimal('old_value', 6, 3)->nullable();
            $table->decimal('new_value', 6, 3)->nullable();
            $table->json('metadata')->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('ip_address', 45);
            $table->timestamp('created_at');

            $table->index(['event_id', 'round_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_audit_logs');
    }
};
