<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_subjects', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::table('teacher_subjects', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->dropUnique('teacher_subjects_teacher_id_subject_id_unique');
        });

        Schema::table('teacher_subjects', function (Blueprint $table) {
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
            $table->unique(['tenant_id', 'teacher_id', 'subject_id'], 'teacher_subjects_tenant_unique');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_subjects', function (Blueprint $table) {
            $table->dropUnique('teacher_subjects_tenant_unique');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
            $table->unique(['teacher_id', 'subject_id']);
        });
    }
};
