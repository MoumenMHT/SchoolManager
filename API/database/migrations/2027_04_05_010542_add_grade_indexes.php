<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function hasIndex(string $tableName, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $tableName)
            ->where('index_name', $indexName)
            ->exists();
    }

    public function up(): void
    {
        if (! $this->hasIndex('grades', 'idx_grades_student_sem_year')) {
            Schema::table('grades', function (Blueprint $table) {
                // Covers getStudentGrades / report card: WHERE student_id + semester + academic_year
                $table->index(['student_id', 'semester', 'academic_year'], 'idx_grades_student_sem_year');
            });
        }

        if (! $this->hasIndex('grades', 'idx_grades_subject_sem_year')) {
            Schema::table('grades', function (Blueprint $table) {
                // Covers getClassGrades JOIN + filter: WHERE subject_id + semester + academic_year
                $table->index(['subject_id', 'semester', 'academic_year'], 'idx_grades_subject_sem_year');
            });
        }

        if (! $this->hasIndex('grades', 'idx_grades_sem_year')) {
            Schema::table('grades', function (Blueprint $table) {
                // Covers getClassRanking JOIN on student_id + semester + academic_year
                $table->index(['semester', 'academic_year'], 'idx_grades_sem_year');
            });
        }

        if (! $this->hasIndex('grades', 'uq_grades_student_subject_exam')) {
            if (DB::getDriverName() === 'mysql') {
                // MySQL key length limit requires prefixes on varchar columns in this composite unique index.
                DB::statement(
                    'ALTER TABLE `grades` ADD UNIQUE `uq_grades_student_subject_exam` '
                    . '(`student_id`, `subject_id`, `exam_type`(50), `semester`(20), `academic_year`(20))'
                );
            } else {
                Schema::table('grades', function (Blueprint $table) {
                    // Prevents duplicate exam entries for the same student/subject/exam/semester/year
                    $table->unique(
                        ['student_id', 'subject_id', 'exam_type', 'semester', 'academic_year'],
                        'uq_grades_student_subject_exam'
                    );
                });
            }
        }

        // Covers coefficientsForLevel(): WHERE level_id → pluck coefficient, subject_id
        if (! $this->hasIndex('level_subjects', 'idx_level_subjects_level_subject')) {
            Schema::table('level_subjects', function (Blueprint $table) {
                $table->index(['level_id', 'subject_id'], 'idx_level_subjects_level_subject');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('grades', 'idx_grades_student_sem_year')) {
            Schema::table('grades', function (Blueprint $table) {
                $table->dropIndex('idx_grades_student_sem_year');
            });
        }

        if ($this->hasIndex('grades', 'idx_grades_subject_sem_year')) {
            Schema::table('grades', function (Blueprint $table) {
                $table->dropIndex('idx_grades_subject_sem_year');
            });
        }

        if ($this->hasIndex('grades', 'idx_grades_sem_year')) {
            Schema::table('grades', function (Blueprint $table) {
                $table->dropIndex('idx_grades_sem_year');
            });
        }

        if ($this->hasIndex('grades', 'uq_grades_student_subject_exam')) {
            Schema::table('grades', function (Blueprint $table) {
                $table->dropUnique('uq_grades_student_subject_exam');
            });
        }

        if ($this->hasIndex('level_subjects', 'idx_level_subjects_level_subject')) {
            Schema::table('level_subjects', function (Blueprint $table) {
                $table->dropIndex('idx_level_subjects_level_subject');
            });
        }
    }
};