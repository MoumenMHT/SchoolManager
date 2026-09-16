<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_averages', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::table('student_averages', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropUnique('student_averages_unique_idx');
        });

        Schema::table('student_averages', function (Blueprint $table) {
            $table->string('trimester', 20)->change();
            // $table->string('academic_year', 20)->change();
        });

        Schema::table('student_averages', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->unique(
                ['tenant_id', 'student_id', 'subject_id', 'record_type', 'trimester', 'academic_year_id'],
                'student_averages_tenant_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('student_averages', function (Blueprint $table) {
            $table->dropUnique('student_averages_tenant_unique');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
            $table->unique(
                ['student_id', 'subject_id', 'record_type', 'trimester', 'academic_year_id'],
                'student_averages_unique_idx'
            );
        });
    }
};
