<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->string('level_name');       // e.g. "Exercise 1", "Grammar", "Level A"
            $table->decimal('max_note', 5, 2);  // max points for this exercise
            $table->timestamps();

            $table->index('exam_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_exercises');
    }
};
