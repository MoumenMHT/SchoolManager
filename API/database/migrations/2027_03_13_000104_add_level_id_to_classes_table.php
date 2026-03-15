<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('level_id')->nullable()->after('level')->constrained('levels')->nullOnDelete();
        });

        if (!Schema::hasTable('levels')) {
            return;
        }

        $levelNames = DB::table('classes')
            ->whereNotNull('level')
            ->select('level')
            ->distinct()
            ->pluck('level')
            ->filter()
            ->values();

        $sortOrder = 1;
        foreach ($levelNames as $levelName) {
            $existingLevel = DB::table('levels')->where('name', $levelName)->first();

            if (!$existingLevel) {
                DB::table('levels')->insert([
                    'cycle' => 'primary',
                    'year_number' => $sortOrder,
                    'track' => null,
                    'name' => $levelName,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $levelId = DB::table('levels')->where('name', $levelName)->value('id');
            DB::table('classes')->where('level', $levelName)->update(['level_id' => $levelId]);
            $sortOrder++;
        }
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('level_id');
        });
    }
};
