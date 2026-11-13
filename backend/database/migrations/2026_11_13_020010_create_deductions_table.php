<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->foreignId('contestant_id')->constrained('contestants')->cascadeOnDelete();
            $table->foreignId('applied_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('points_deducted', 5, 2)->default(0.00);
            $table->string('reason', 255);
            $table->timestamps();

            $table->unique(['round_id', 'contestant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deductions');
    }
};
