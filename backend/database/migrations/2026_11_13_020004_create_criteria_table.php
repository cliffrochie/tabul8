<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('criteria')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('weight_percentage', 5, 2);
            $table->decimal('min_score', 5, 2)->default(70.00);
            $table->decimal('max_score', 5, 2)->default(100.00);
            $table->decimal('score_step', 3, 2)->default(0.50);
            $table->unsignedInteger('sequence')->default(1);
            $table->timestamps();

            $table->index(['round_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criteria');
    }
};
