<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedInteger('sequence');
            $table->enum('scoring_strategy', ['weighted', 'ordinal', 'trimmed'])->default('weighted');
            $table->decimal('weight_in_overall', 5, 2)->default(100.00);
            $table->boolean('is_back_to_zero')->default(false);
            $table->unsignedInteger('advancement_count')->default(3);
            $table->enum('status', ['upcoming', 'active', 'locked', 'finalized'])->default('upcoming');
            $table->boolean('is_locked')->default(false);
            $table->json('settings')->default('{}');
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
