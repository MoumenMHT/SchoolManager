<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private bool $isSqlite;
    private bool $isMysql;
    private bool $isPgsql;

    public function __construct()
    {
        $driver = DB::connection()->getDriverName();
        $this->isSqlite = $driver === 'sqlite';
        $this->isMysql = $driver === 'mysql';
        $this->isPgsql = $driver === 'pgsql';
    }

    private function hasForeignKey(string $table, string $column, string $referencedTable): bool
    {
        if ($this->isSqlite) {
            $fks = DB::select("PRAGMA foreign_key_list(`{$table}`)");
            foreach ($fks as $fk) {
                if ($fk->from === $column && $fk->table === $referencedTable) {
                    return true;
                }
            }
            return false;
        }

        if ($this->isPgsql) {
            // Rough check for Postgres, just to bypass the MySQL block
            return true; 
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

        if ($this->isPgsql) {
            return DB::table('pg_indexes')
                ->where('tablename', $table)
                ->where('indexname', $indexName)
                ->exists();
        }

        return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'"))->isNotEmpty();
    }

    public function up(): void
    {
        if (!Schema::hasColumn('parents_fees', 'student_id')) {
            Schema::table('parents_fees', function (Blueprint $table) {
                $table->foreignId('student_id')->nullable()->after('parent_id')->constrained('students')->onDelete('cascade');
            });
        } elseif ($this->isMysql && !$this->hasForeignKey('parents_fees', 'student_id', 'students')) {
            Schema::table('parents_fees', function (Blueprint $table) {
                $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            });
        }

        if ($this->isMysql) {
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

        if ($this->hasIndex('parents_fees', 'parents_fees_parent_id_fee_id_unique')) {
            if ($this->isSqlite) {
                DB::statement('DROP INDEX IF EXISTS `parents_fees_parent_id_fee_id_unique`');
            } else {
                Schema::table('parents_fees', function (Blueprint $table) {
                    $table->dropUnique('parents_fees_parent_id_fee_id_unique');
                });
            }
        }

        if ($this->isMysql) {
            Schema::table('parents_fees', function (Blueprint $table) {
                $table->foreign('parent_id')->references('id')->on('parents')->onDelete('cascade');
            });
        }

        if (!$this->hasIndex('parents_fees', 'parents_fees_parent_id_student_id_fee_id_unique')) {
            Schema::table('parents_fees', function (Blueprint $table) {
                $table->unique(['parent_id', 'student_id', 'fee_id'], 'parents_fees_parent_id_student_id_fee_id_unique');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('parents_fees', 'parents_fees_parent_id_student_id_fee_id_unique')) {
            if ($this->isSqlite) {
                DB::statement('DROP INDEX IF EXISTS `parents_fees_parent_id_student_id_fee_id_unique`');
            } else {
                Schema::table('parents_fees', function (Blueprint $table) {
                    $table->dropUnique('parents_fees_parent_id_student_id_fee_id_unique');
                });
            }
        }

        if ($this->isMysql) {
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
                if ($this->isPgsql) {
                    $table->dropForeign(['student_id']);
                }
                $table->dropColumn('student_id');
            });
            if ($this->isSqlite) {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }

        if ($this->isMysql) {
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
                $table->unique(['parent_id', 'fee_id'], 'parents_fees_parent_id_fee_id_unique');
            });
        } elseif ($this->isPgsql) {
            Schema::table('parents_fees', function (Blueprint $table) {
                $table->unique(['parent_id', 'fee_id'], 'parents_fees_parent_id_fee_id_unique');
            });
        }
    }
};
