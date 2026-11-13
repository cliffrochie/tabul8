<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->string('subtitle', 255)->nullable();
            $table->enum('event_type', ['pageant', 'singing', 'dance', 'custom'])->default('pageant');
            $table->enum('status', ['setup', 'live', 'completed'])->default('setup');
            $table->unsignedBigInteger('active_round_id')->nullable();
            $table->unsignedBigInteger('on_stage_contestant_id')->nullable();
            $table->enum('led_mode', ['on_stage', 'criteria', 'reveal'])->default('on_stage');
            $table->json('revealed_contestant_ids')->default('[]');
            $table->json('settings')->default('{}');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
