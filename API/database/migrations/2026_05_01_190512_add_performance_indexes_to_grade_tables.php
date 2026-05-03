<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds composite and covering indexes to the new exam-based tables
 * to optimize the high-frequency query patterns in GradeController:
 *
 *  1. grades          – lookup by student + exam_id (report card, student grades)
 *  2. exams           – filter by exam_type / semester / academic_year (analytics)
 *  3. exams           – teacher_id needs its own index (FK was re-added without index)
 *  4. exam_exercises  – already has exam_id index (OK)
 *  5. exercise_grades – already has grade_id+exercise unique + exercise index (OK)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── grades table ──────────────────────────────────────────────────
        Schema::table('grades', function (Blueprint $table) {
            // Covers: WHERE student_id = ? (AND exam_id = ?)
            // Used by getStudentGrades, getStudentReportCard, GradingService
            $table->index(['student_id', 'exam_id'], 'idx_grades_student_exam');
        });

        // ── exams table ───────────────────────────────────────────────────
        Schema::table('exams', function (Blueprint $table) {
            // Covers: WHERE exam_type = ? AND semester = ? AND academic_year = ?
            // Used in analytics overview filters and GradingService::synchronizeAverages
            $table->index(['exam_type', 'semester', 'academic_year'], 'idx_exams_type_sem_year');

            // Covers: WHERE teacher_id = ? (analytics teacher aggregation)
            $table->index('teacher_id', 'idx_exams_teacher_id');

            // Covers: WHERE subject_id = ? (analytics subject aggregation)
            // The existing (subject_id, semester, academic_year) index already covers this,
            // but adding a dedicated FK index ensures the FK itself is properly supported
            // (the FK constraint on subject_id needs its own index in MySQL)
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropIndex('idx_grades_student_exam');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex('idx_exams_type_sem_year');
            $table->dropIndex('idx_exams_teacher_id');
        });
    }
};
