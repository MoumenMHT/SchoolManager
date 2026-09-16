<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected array $tables = [
        'classes',
        'grades',
        'class_subject_teacher',
        'fees',
        'student_averages',
        'exams',
        'contracts',
        'student_history'
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Gather distinct academic years per tenant and create AcademicYear records
        $academicYearsByTenant = [];
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            if (!Schema::hasColumn($tableName, 'academic_year')) {
                continue;
            }

            // Only attempt if tenant_id exists
            if (!Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            $distinctData = DB::table($tableName)
                ->select('tenant_id', 'academic_year')
                ->whereNotNull('academic_year')
                ->where('academic_year', '!=', '')
                ->distinct()
                ->get();

            foreach ($distinctData as $row) {
                if (!isset($academicYearsByTenant[$row->tenant_id])) {
                    $academicYearsByTenant[$row->tenant_id] = [];
                }
                if (!in_array($row->academic_year, $academicYearsByTenant[$row->tenant_id])) {
                    $academicYearsByTenant[$row->tenant_id][] = $row->academic_year;
                }
            }
        }

        // Insert into academic_years
        $now = now();
        foreach ($academicYearsByTenant as $tenantId => $years) {
            foreach ($years as $yearName) {
                DB::table('academic_years')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'name' => $yearName],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        // Get map
        $allYears = DB::table('academic_years')->get();
        $yearMap = []; // [tenant_id][name] = id
        foreach ($allYears as $y) {
            $yearMap[$y->tenant_id][$y->name] = $y->id;
        }

        // 2. Add academic_year_id column, update data, and drop old column/indexes
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            // A. Add column if it doesn't exist
            if (!Schema::hasColumn($tableName, 'academic_year_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
                });
            }

            // B. Update data
            if (Schema::hasColumn($tableName, 'tenant_id') && Schema::hasColumn($tableName, 'academic_year')) {
                $rows = DB::table($tableName)->select('id', 'tenant_id', 'academic_year')->get();
                foreach ($rows as $row) {
                    if (!empty($row->academic_year) && isset($yearMap[$row->tenant_id][$row->academic_year])) {
                        DB::table($tableName)->where('id', $row->id)->update([
                            'academic_year_id' => $yearMap[$row->tenant_id][$row->academic_year]
                        ]);
                    }
                }
            }

            // C. Recreate indexes to use academic_year_id instead of academic_year
            if ($tableName === 'student_averages') {
                $hasTenant = Schema::hasColumn('student_averages', 'tenant_id');
                $cols = array_values(array_filter([
                    $hasTenant ? 'tenant_id' : null,
                    'student_id', 'subject_id', 'record_type', 'trimester', 'academic_year_id'
                ]));
                try {
                    Schema::table('student_averages', function (Blueprint $table) {
                        $table->dropUnique('student_averages_unique_idx');
                    });
                } catch (\Throwable $e) {}
                try {
                    Schema::table('student_averages', function (Blueprint $table) use ($cols) {
                        $table->unique($cols, 'student_averages_unique_idx');
                    });
                } catch (\Throwable $e) {}
            } elseif ($tableName === 'class_subject_teacher') {
                $hasTenant = Schema::hasColumn('class_subject_teacher', 'tenant_id');
                $cols = array_values(array_filter([
                    $hasTenant ? 'tenant_id' : null,
                    'class_id', 'subject_id', 'teacher_id', 'academic_year_id'
                ]));
                try {
                    Schema::table('class_subject_teacher', function (Blueprint $table) {
                        $table->dropUnique('class_subject_teacher_unique');
                    });
                } catch (\Throwable $e) {}
                try {
                    Schema::table('class_subject_teacher', function (Blueprint $table) use ($cols) {
                        $table->unique($cols, 'class_subject_teacher_unique');
                    });
                } catch (\Throwable $e) {}
            } elseif ($tableName === 'exams') {
                try {
                    Schema::table('exams', function (Blueprint $table) {
                        $table->dropIndex('idx_exams_type_sem_year');
                    });
                } catch (\Throwable $e) {}
                try {
                    Schema::table('exams', function (Blueprint $table) {
                        $table->dropIndex('exams_subject_id_semester_academic_year_index');
                    });
                } catch (\Throwable $e) {}
                try {
                    Schema::table('exams', function (Blueprint $table) {
                        $table->dropIndex('exams_teacher_id_semester_academic_year_index');
                    });
                } catch (\Throwable $e) {}
                
                try {
                    Schema::table('exams', function (Blueprint $table) {
                        $table->index(['exam_type', 'semester', 'academic_year_id'], 'idx_exams_type_sem_year');
                        $table->index(['subject_id', 'semester', 'academic_year_id']);
                        $table->index(['teacher_id', 'semester', 'academic_year_id']);
                    });
                } catch (\Throwable $e) {}
            }

            if (Schema::hasColumn($tableName, 'academic_year')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('academic_year');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting this complex migration is tricky and can lose data mapping.
        // We'll just add the string column back.
        foreach ($this->tables as $tableName) {
            if (Schema::hasColumn($tableName, 'academic_year_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->string('academic_year')->nullable();
                });

                // Set strings
                $rows = DB::table($tableName)->select('id', 'academic_year_id')->whereNotNull('academic_year_id')->get();
                foreach ($rows as $row) {
                    $y = DB::table('academic_years')->where('id', $row->academic_year_id)->first();
                    if ($y) {
                        DB::table($tableName)->where('id', $row->id)->update(['academic_year' => $y->name]);
                    }
                }

                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    // Reverse index drops if possible, omitted for brevity.
                    $table->dropForeign(['academic_year_id']);
                    $table->dropColumn('academic_year_id');
                });
            }
        }
    }
};
