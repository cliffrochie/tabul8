<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contestants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedInteger('candidate_number');
            $table->string('name', 150);
            $table->string('origin_or_group', 150);
            $table->string('performance_meta', 255)->nullable();
            $table->string('category', 80)->default('Solo');
            $table->string('photo_path', 255)->nullable();
            $table->enum('asset_ratio', ['PORTRAIT_4:5', 'PHOTO_300x300', 'WIDE_16:9'])->default('PORTRAIT_4:5');
            $table->enum('status', ['active', 'eliminated', 'withdrawn'])->default('active');
            $table->timestamps();

            $table->unique(['event_id', 'candidate_number', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contestants');
    }
};
