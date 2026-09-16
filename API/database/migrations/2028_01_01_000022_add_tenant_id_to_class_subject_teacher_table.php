<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_subject_teacher', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Drop the foreign key that might be relying on the unique index
        Schema::table('class_subject_teacher', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropUnique('class_subject_teacher_unique');
        });

        // Add new tenant-scoped unique constraint and recreate foreign key
        Schema::table('class_subject_teacher', function (Blueprint $table) {
            $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade');
            $table->unique(
                ['tenant_id', 'class_id', 'subject_id', 'teacher_id', 'academic_year_id'],
                'cst_tenant_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('class_subject_teacher', function (Blueprint $table) {
            $table->dropUnique('cst_tenant_unique');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
            $table->unique(['class_id', 'subject_id', 'teacher_id', 'academic_year_id'], 'class_subject_teacher_unique');
        });
    }
};
