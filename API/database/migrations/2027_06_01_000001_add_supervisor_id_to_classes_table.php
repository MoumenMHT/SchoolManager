<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add supervisor_id to classes table
        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('supervisor_id')->nullable()->after('main_teacher_id')->constrained('supervisors')->onDelete('set null');
        });

        // 2. Migrate existing pivot data (if supervisor_classes table exists)
        if (Schema::hasTable('supervisor_classes')) {
            // For each pivot row, set the supervisor_id on the class
            $pivotRows = DB::table('supervisor_classes')->get();
            foreach ($pivotRows as $row) {
                DB::table('classes')
                    ->where('id', $row->class_id ?? null)
                    ->whereNull('supervisor_id')
                    ->update(['supervisor_id' => $row->supervisor_id ?? null]);
            }

            // Drop the pivot table
            Schema::dropIfExists('supervisor_classes');
        }

        // 3. Drop class_id from supervisors table if it exists
        if (Schema::hasColumn('supervisors', 'class_id')) {
            Schema::table('supervisors', function (Blueprint $table) {
                $table->dropForeign(['class_id']);
                $table->dropColumn('class_id');
            });
        }
    }

    public function down(): void
    {
        // Re-create supervisor_classes pivot
        Schema::create('supervisor_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // Re-add class_id to supervisors
        Schema::table('supervisors', function (Blueprint $table) {
            $table->foreignId('class_id')->nullable()->constrained()->onDelete('set null');
        });

        // Drop supervisor_id from classes
        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropColumn('supervisor_id');
        });
    }
};
