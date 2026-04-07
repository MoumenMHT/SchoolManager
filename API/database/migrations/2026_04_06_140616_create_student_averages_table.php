<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_averages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('record_type', ['subject', 'overall']);
            $table->string('trimester');
            $table->string('academic_year');
            $table->decimal('average', 5, 2);
            $table->timestamps();

            // Setup a unique composite key to easily upsert data without producing duplicates
            // Since `subject_id` can be null, many DB systems skip unique constraints if a key is null.
            // On PostgreSQL/MySQL it treats NULLs as distinct. To prevent multiple 'overall' rows per student per trimester:
            // We cannot depend just on standard unique index if subject_id is null in InnoDB.
            // But since record_type is added, we can make it unique!
            $table->unique(['student_id', 'subject_id', 'record_type', 'trimester', 'academic_year'], 'student_averages_unique_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_averages');
    }
};
