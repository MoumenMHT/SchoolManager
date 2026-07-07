<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This migration uses pure SQL bulk operations (no PHP loops) to be fast:
     * 1. Adds exam_id (nullable FK) to grades.
     * 2. Bulk-inserts unique Exams from the existing grades data.
     * 3. Bulk-populates class_exam pivot from the student→class relation.
     * 4. Bulk-updates grades.exam_id using a JOIN.
     * 5. Drops the redundant columns from grades.
     *
     * Note: MySQL-specific steps 2-5 are skipped on SQLite (test environment).
     * For SQLite + RefreshDatabase there is no existing data to migrate.
     * Because SQLite cannot drop FK-referenced columns, we recreate the table.
     */
    public function up(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        if ($isSqlite) {
            // SQLite path: recreate grades table with the final schema.
            // With RefreshDatabase there is no data, so we just recreate.
            DB::statement('PRAGMA foreign_keys = OFF');
            Schema::drop('grades');
            Schema::create('grades', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained()->onDelete('cascade');
                $table->foreignId('exam_id')->constrained()->onDelete('cascade');
                $table->decimal('grade', 5, 2);
                $table->text('comment')->nullable();
                $table->timestamps();
            });
            DB::statement('PRAGMA foreign_keys = ON');
            return;
        }

        // ── MySQL path ────────────────────────────────────────────────────────

        // Step 1: Add nullable exam_id FK (guard in case a previous partial run already added it)
        if (!Schema::hasColumn('grades', 'exam_id')) {
            Schema::table('grades', function (Blueprint $table) {
                $table->foreignId('exam_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            });
        }

        // Step 2: Bulk-insert one Exam row per unique (subject_id, teacher_id, exam_type, semester, academic_year)
        DB::statement("
            INSERT INTO exams (subject_id, teacher_id, exam_type, semester, academic_year, max_grade, created_at, updated_at)
            SELECT DISTINCT
                subject_id,
                teacher_id,
                exam_type,
                semester,
                academic_year,
                MAX(max_grade),
                NOW(),
                NOW()
            FROM grades
            GROUP BY subject_id, teacher_id, exam_type, semester, academic_year
        ");

        // Step 3: Bulk-update grades.exam_id by joining on all identifying columns
        DB::statement("
            UPDATE grades
            INNER JOIN exams
                ON  exams.subject_id    = grades.subject_id
                AND exams.teacher_id    = grades.teacher_id
                AND exams.exam_type     = grades.exam_type
                AND exams.semester      = grades.semester
                AND exams.academic_year = grades.academic_year
            SET grades.exam_id = exams.id
        ");

        // Step 4: Bulk-populate class_exam pivot
        // Each unique (exam_id, class_id) found via grades → students
        DB::statement("
            INSERT IGNORE INTO class_exam (exam_id, class_id)
            SELECT DISTINCT grades.exam_id, students.class_id
            FROM grades
            INNER JOIN students ON students.id = grades.student_id
        ");

        // Step 5: Drop ALL FK constraints first, THEN indexes, THEN columns
        $allFks = DB::select("
            SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_NAME = 'grades'
              AND TABLE_SCHEMA = DATABASE()
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ");
        foreach ($allFks as $fk) {
            DB::statement("ALTER TABLE grades DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }

        $existingIndexes = collect(DB::select('SHOW INDEX FROM grades'))->pluck('Key_name')->unique()->toArray();
        foreach (['uq_grades_student_subject_exam', 'idx_grades_student_sem_year', 'idx_grades_subject_sem_year', 'idx_grades_sem_year', 'grades_teacher_id_foreign'] as $idx) {
            if (in_array($idx, $existingIndexes)) {
                DB::statement("ALTER TABLE grades DROP INDEX `{$idx}`");
            }
        }

        $existingCols = collect(DB::select('DESCRIBE grades'))->pluck('Field')->toArray();
        $dropCols = array_values(array_intersect(['subject_id', 'teacher_id', 'exam_type', 'semester', 'academic_year', 'max_grade'], $existingCols));
        if (!empty($dropCols)) {
            Schema::table('grades', function (Blueprint $table) use ($dropCols) {
                $table->dropColumn($dropCols);
            });
        }

        // Re-add the student_id FK (we dropped it above too as collateral)
        if (!collect(DB::select("
            SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_NAME = 'grades' AND TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'grades_student_id_foreign'
        "))->count()) {
            Schema::table('grades', function (Blueprint $table) {
                $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            });
        }

        // Make exam_id NOT NULL
        Schema::table('grades', function (Blueprint $table) {
            $table->foreignId('exam_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        if ($isSqlite) {
            // Recreate the old grades table schema for SQLite
            DB::statement('PRAGMA foreign_keys = OFF');
            Schema::drop('grades');
            Schema::create('grades', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained()->onDelete('cascade');
                $table->foreignId('subject_id')->constrained()->onDelete('cascade');
                $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
                $table->string('exam_type');
                $table->decimal('grade', 5, 2);
                $table->decimal('max_grade', 5, 2)->default(20);
                $table->string('semester');
                $table->string('academic_year');
                $table->text('comment')->nullable();
                $table->timestamps();
            });
            DB::statement('PRAGMA foreign_keys = ON');
            return;
        }

        Schema::table('grades', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('exam_type')->nullable();
            $table->decimal('max_grade', 5, 2)->default(20)->nullable();
            $table->string('semester')->nullable();
            $table->string('academic_year')->nullable();
        });

        DB::statement("
            UPDATE grades
            INNER JOIN exams ON exams.id = grades.exam_id
            SET
                grades.subject_id    = exams.subject_id,
                grades.teacher_id    = exams.teacher_id,
                grades.exam_type     = exams.exam_type,
                grades.semester      = exams.semester,
                grades.academic_year = exams.academic_year,
                grades.max_grade     = exams.max_grade
        ");

        Schema::table('grades', function (Blueprint $table) {
            $table->dropForeign(['exam_id']);
            $table->dropColumn('exam_id');
        });
    }
};
