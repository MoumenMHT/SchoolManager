<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subject_coefficients')) {
            Schema::dropIfExists('subject_coefficients');
        }
    }

    public function down(): void
    {
        Schema::create('subject_coefficients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->string('class_level', 100);
            $table->integer('coefficient');
            $table->timestamps();
            $table->unique(['subject_id', 'class_level']);
        });

        if (Schema::hasTable('level_subjects') && Schema::hasTable('levels')) {
            $rows = DB::table('level_subjects')
                ->join('levels', 'levels.id', '=', 'level_subjects.level_id')
                ->select('level_subjects.subject_id', 'levels.name as class_level', 'level_subjects.coefficient', 'level_subjects.created_at', 'level_subjects.updated_at')
                ->get();

            foreach ($rows as $row) {
                DB::table('subject_coefficients')->updateOrInsert(
                    [
                        'subject_id' => $row->subject_id,
                        'class_level' => $row->class_level,
                    ],
                    [
                        'coefficient' => $row->coefficient,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]
                );
            }
        }
    }
};
