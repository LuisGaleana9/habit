<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('habit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('completed_date');
            $table->integer('xp_earned');
            $table->json('loot_dropped')->nullable();
            $table->timestamps();

            $table->unique(['habit_id', 'user_id', 'completed_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_logs');
    }
};
