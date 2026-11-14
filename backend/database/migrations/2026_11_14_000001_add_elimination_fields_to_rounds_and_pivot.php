<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->enum('cutoff_mode', ['top_n_advance', 'eliminate_n'])
                ->default('top_n_advance')
                ->after('is_back_to_zero');
            $table->unsignedInteger('eliminate_count')
                ->default(0)
                ->after('advancement_count');
        });

        Schema::table('round_contestants', function (Blueprint $table) {
            $table->enum('qualification_method', ['INITIAL_ROSTER', 'AUTO_TOP_N', 'MANUAL_WILDCARD'])
                ->default('INITIAL_ROSTER')
                ->after('qualified_from_rank');
            $table->string('override_reason', 255)
                ->nullable()
                ->after('qualification_method');
        });
    }

    public function down(): void
    {
        Schema::table('round_contestants', function (Blueprint $table) {
            $table->dropColumn(['qualification_method', 'override_reason']);
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn(['cutoff_mode', 'eliminate_count']);
        });
    }
};
