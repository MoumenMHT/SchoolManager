<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function hasIndex(string $tableName, string $indexName): bool
    {
        // SQLite does not support information_schema — use pragma instead
        if (DB::connection()->getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list(`{$tableName}`)");
            foreach ($indexes as $index) {
                if ($index->name === $indexName) {
                    return true;
                }
            }
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $tableName)
            ->where('index_name', $indexName)
            ->exists();
    }

    public function up(): void
    {
        // Covers coefficientsForLevel(): WHERE level_id → pluck coefficient, subject_id
        if (! $this->hasIndex('level_subjects', 'idx_level_subjects_level_subject')) {
            Schema::table('level_subjects', function (Blueprint $table) {
                $table->index(['level_id', 'subject_id'], 'idx_level_subjects_level_subject');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('level_subjects', 'idx_level_subjects_level_subject')) {
            Schema::table('level_subjects', function (Blueprint $table) {
                $table->dropIndex('idx_level_subjects_level_subject');
            });
        }
    }
};