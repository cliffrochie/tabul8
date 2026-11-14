<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACTIONS = [
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
        'ROUND_FINALIZED',
        'QUALIFIER_OVERRIDE',
        'ROUND_REOPENED',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable(self::ACTIONS);

            return;
        }

        Schema::table('score_audit_logs', function (Blueprint $table) {
            $table->enum('action', self::ACTIONS)->change();
        });
    }

    public function down(): void
    {
        $legacyActions = array_slice(self::ACTIONS, 0, 10);

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable($legacyActions);

            return;
        }

        Schema::table('score_audit_logs', function (Blueprint $table) use ($legacyActions) {
            $table->enum('action', $legacyActions)->change();
        });
    }

    /**
     * @param  list<string>  $actions
     */
    private function rebuildSqliteTable(array $actions): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('score_audit_logs_tmp', function (Blueprint $table) use ($actions) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->foreignId('score_id')->nullable()->constrained('scores')->nullOnDelete();
            $table->foreignId('judge_id')->nullable()->constrained('judges')->nullOnDelete();
            $table->foreignId('contestant_id')->nullable()->constrained('contestants')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('action', $actions);
            $table->decimal('old_value', 6, 3)->nullable();
            $table->decimal('new_value', 6, 3)->nullable();
            $table->json('metadata')->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('ip_address', 45);
            $table->timestamp('created_at');
        });

        $placeholders = implode(', ', array_fill(0, count($actions), '?'));
        DB::insert(
            "INSERT INTO score_audit_logs_tmp (id, event_id, round_id, score_id, judge_id, contestant_id, actor_user_id, action, old_value, new_value, metadata, reason, ip_address, created_at)
             SELECT id, event_id, round_id, score_id, judge_id, contestant_id, actor_user_id, action, old_value, new_value, metadata, reason, ip_address, created_at
             FROM score_audit_logs WHERE action IN ({$placeholders})",
            $actions
        );

        Schema::drop('score_audit_logs');
        Schema::rename('score_audit_logs_tmp', 'score_audit_logs');

        Schema::table('score_audit_logs', function (Blueprint $table) {
            $table->index(['event_id', 'round_id', 'action'], 'score_audit_logs_event_id_round_id_action_index');
        });

        Schema::enableForeignKeyConstraints();
    }
};
