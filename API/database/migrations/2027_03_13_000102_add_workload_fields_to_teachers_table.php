<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->enum('contract_type', ['permanent', 'part_time'])->default('permanent')->after('salary');
            $table->unsignedTinyInteger('weekly_hours')->default(20)->after('contract_type');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn(['contract_type', 'weekly_hours']);
        });
    }
};
