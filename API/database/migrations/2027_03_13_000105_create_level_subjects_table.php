<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->integer('coefficient');
            $table->unsignedTinyInteger('weekly_sessions_required')->default(1);
            $table->timestamps();

            $table->unique(['level_id', 'subject_id']);
        });

        if (Schema::hasTable('subject_coefficients')) {
            $legacyRows = DB::table('subject_coefficients')->get();

            foreach ($legacyRows as $row) {
                $levelId = DB::table('levels')->where('name', $row->class_level)->value('id');

                if (!$levelId) {
                    $nextSortOrder = (int) DB::table('levels')->max('sort_order') + 1;
                    DB::table('levels')->insert([
                        'cycle' => 'primary',
                        'year_number' => $nextSortOrder,
                        'track' => null,
                        'name' => $row->class_level,
                        'sort_order' => $nextSortOrder,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $levelId = DB::table('levels')->where('name', $row->class_level)->value('id');
                }

                DB::table('level_subjects')->updateOrInsert(
                    [
                        'level_id' => $levelId,
                        'subject_id' => $row->subject_id,
                    ],
                    [
                        'coefficient' => $row->coefficient,
                        'weekly_sessions_required' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('level_subjects');
    }
};
