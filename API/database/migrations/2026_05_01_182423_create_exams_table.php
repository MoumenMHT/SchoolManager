<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->string('exam_type');       // e.g. devoir, composition, evaluation_continue
            $table->string('semester');        // e.g. trimestre_1
            $table->string('academic_year');   // e.g. 2024-2025
            $table->decimal('max_grade', 5, 2)->default(20);
            $table->timestamps();

            $table->index(['subject_id', 'semester', 'academic_year']);
            $table->index(['teacher_id', 'semester', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
