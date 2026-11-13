<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round_contestants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->foreignId('contestant_id')->constrained('contestants')->cascadeOnDelete();
            $table->unsignedInteger('performance_order')->default(1);
            $table->unsignedInteger('qualified_from_rank')->nullable();
            $table->timestamps();

            $table->unique(['round_id', 'contestant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_contestants');
    }
};
