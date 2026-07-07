<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private bool $isSqlite;

    public function __construct()
    {
        $this->isSqlite = DB::connection()->getDriverName() === 'sqlite';
    }

    private function hasForeignKey(string $table, string $column, string $referencedTable): bool
    {
        if ($this->isSqlite) {
            // SQLite: use PRAGMA foreign_key_list
            $fks = DB::select("PRAGMA foreign_key_list(`{$table}`)");
            foreach ($fks as $fk) {
                if ($fk->from === $column && $fk->table === $referencedTable) {
                    return true;
                }
            }
            return false;
        }

        return collect(DB::select("
            SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$table}'
              AND COLUMN_NAME = '{$column}'
              AND REFERENCED_TABLE_NAME = '{$referencedTable}'
        "))->isNotEmpty();
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        if ($this->isSqlite) {
            $indexes = DB::select("PRAGMA index_list(`{$table}`)");
            foreach ($indexes as $idx) {
                if ($idx->name === $indexName) return true;
            }
            return false;
        }

        return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'"))->isNotEmpty();
    }

    public function up(): void
    {
        // Step 1: Add column if not already present (first run partially added it)
        if (!Schema::hasColumn('parents_fees', 'student_id')) {
            Schema::table('parents_fees', function (Blueprint $table) {
                $table->foreignId('student_id')->nullable()->after('parent_id')->constrained('students')->onDelete('cascade');
            });
        } elseif (!$this->hasForeignKey('parents_fees', 'student_id', 'students')) {
            // Column exists but FK constraint may be missing — add it if absent
            Schema::table('parents_fees', function (Blueprint $table) {
                $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            });
        }

        if (!$this->isSqlite) {
            // Step 2: Drop parent_id FK so we can drop the unique index (MySQL only)
            $parentFks = collect(DB::select("
                SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'parents_fees'
                  AND COLUMN_NAME = 'parent_id'
                  AND REFERENCED_TABLE_NAME = 'parents'
            "));
            foreach ($parentFks as $fk) {
                DB::statement("ALTER TABLE `parents_fees` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            }
        }

        // Step 3: Drop old (parent_id, fee_id) unique index if it still exists
        if ($this->hasIndex('parents_fees', 'parents_fees_parent_id_fee_id_unique')) {
            if ($this->isSqlite) {
                DB::statement('DROP INDEX IF EXISTS `parents_fees_parent_id_fee_id_unique`');
            } else {
                DB::statement("ALTER TABLE `parents_fees` DROP INDEX `parents_fees_parent_id_fee_id_unique`");
            }
        }

        if (!$this->isSqlite) {
            // Step 4: Re-add parent_id FK (MySQL only — SQLite tracks FKs differently)
            Schema::table('parents_fees', function (Blueprint $table) {
                $table->foreign('parent_id')->references('id')->on('parents')->onDelete('cascade');
            });
        }

        // Step 5: Add new composite unique index (only if not already present)
        if (!$this->hasIndex('parents_fees', 'parents_fees_parent_id_student_id_fee_id_unique')) {
            Schema::table('parents_fees', function (Blueprint $table) {
                $table->unique(['parent_id', 'student_id', 'fee_id'], 'parents_fees_parent_id_student_id_fee_id_unique');
            });
        }
    }

    public function down(): void
    {
        // Drop new unique index
        if ($this->hasIndex('parents_fees', 'parents_fees_parent_id_student_id_fee_id_unique')) {
            if ($this->isSqlite) {
                DB::statement('DROP INDEX IF EXISTS `parents_fees_parent_id_student_id_fee_id_unique`');
            } else {
                DB::statement("ALTER TABLE `parents_fees` DROP INDEX `parents_fees_parent_id_student_id_fee_id_unique`");
            }
        }

        if (!$this->isSqlite) {
            // Drop student_id FK (MySQL only)
            $studentFks = collect(DB::select("
                SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'parents_fees'
                  AND COLUMN_NAME = 'student_id'
                  AND REFERENCED_TABLE_NAME = 'students'
            "));
            foreach ($studentFks as $fk) {
                DB::statement("ALTER TABLE `parents_fees` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            }
        }

        if (Schema::hasColumn('parents_fees', 'student_id')) {
            if ($this->isSqlite) {
                DB::statement('PRAGMA foreign_keys = OFF');
            }
            Schema::table('parents_fees', function (Blueprint $table) {
                $table->dropColumn('student_id');
            });
            if ($this->isSqlite) {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }

        if (!$this->isSqlite) {
            // Drop parent_id FK so we can recreate old unique index (MySQL only)
            $parentFks = collect(DB::select("
                SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'parents_fees'
                  AND COLUMN_NAME = 'parent_id'
                  AND REFERENCED_TABLE_NAME = 'parents'
            "));
            foreach ($parentFks as $fk) {
                DB::statement("ALTER TABLE `parents_fees` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            }

            Schema::table('parents_fees', function (Blueprint $table) {
                $table->foreign('parent_id')->references('id')->on('parents')->onDelete('cascade');
                $table->unique(['parent_id', 'fee_id']);
            });
        }
    }
};
