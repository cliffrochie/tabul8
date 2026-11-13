<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreign('active_round_id')->references('id')->on('rounds')->nullOnDelete();
            $table->foreign('on_stage_contestant_id')->references('id')->on('contestants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['active_round_id']);
            $table->dropForeign(['on_stage_contestant_id']);
        });
    }
};
