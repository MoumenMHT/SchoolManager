<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained()->onDelete('cascade');
            $table->foreignId('exam_exercise_id')->constrained('exam_exercises')->onDelete('cascade');
            $table->decimal('note', 5, 2);   // the student's actual score on this exercise
            $table->timestamps();

            $table->unique(['grade_id', 'exam_exercise_id']);
            $table->index('exam_exercise_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_grades');
    }
};
