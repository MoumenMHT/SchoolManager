<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add column if not already present (first run partially added it)
        if (!Schema::hasColumn('parents_fees', 'student_id')) {
            Schema::table('parents_fees', function (Blueprint $table) {
                $table->foreignId('student_id')->nullable()->after('parent_id')->constrained('students')->onDelete('cascade');
            });
        } else {
            // Column exists but FK constraint may be missing — add it if absent
            $fks = collect(DB::select("
                SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'parents_fees'
                  AND COLUMN_NAME = 'student_id'
                  AND REFERENCED_TABLE_NAME = 'students'
            "));
            if ($fks->isEmpty()) {
                Schema::table('parents_fees', function (Blueprint $table) {
                    $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
                });
            }
        }

        // Step 2: Drop parent_id FK so we can drop the unique index
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

        // Step 3: Drop old (parent_id, fee_id) unique index if it still exists
        $oldIndex = collect(DB::select("
            SHOW INDEX FROM `parents_fees`
            WHERE Key_name = 'parents_fees_parent_id_fee_id_unique'
        "));
        if ($oldIndex->isNotEmpty()) {
            DB::statement("ALTER TABLE `parents_fees` DROP INDEX `parents_fees_parent_id_fee_id_unique`");
        }

        // Step 4: Re-add parent_id FK
        Schema::table('parents_fees', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('parents')->onDelete('cascade');
        });

        // Step 5: Add new composite unique index (only if not already present)
        $newIndex = collect(DB::select("
            SHOW INDEX FROM `parents_fees`
            WHERE Key_name = 'parents_fees_parent_id_student_id_fee_id_unique'
        "));
        if ($newIndex->isEmpty()) {
            DB::statement("ALTER TABLE `parents_fees` ADD UNIQUE KEY `parents_fees_parent_id_student_id_fee_id_unique` (`parent_id`, `student_id`, `fee_id`)");
        }
    }

    public function down(): void
    {
        // Drop new unique index
        $newIndex = collect(DB::select("
            SHOW INDEX FROM `parents_fees`
            WHERE Key_name = 'parents_fees_parent_id_student_id_fee_id_unique'
        "));
        if ($newIndex->isNotEmpty()) {
            DB::statement("ALTER TABLE `parents_fees` DROP INDEX `parents_fees_parent_id_student_id_fee_id_unique`");
        }

        // Drop student_id FK and column
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

        if (Schema::hasColumn('parents_fees', 'student_id')) {
            Schema::table('parents_fees', function (Blueprint $table) {
                $table->dropColumn('student_id');
            });
        }

        // Drop parent_id FK so we can recreate old unique index
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
};
