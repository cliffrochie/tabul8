<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('judges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedInteger('judge_number');
            $table->string('name', 150);
            $table->string('role_title', 150)->default('Adjudicator');
            $table->string('access_pin_hash', 255);
            $table->string('plain_pin_reference', 16);
            $table->boolean('is_chairman')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('device_fingerprint', 255)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'judge_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('judges');
    }
};
