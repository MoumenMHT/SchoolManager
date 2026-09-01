<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('level_subjects', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::table('level_subjects', function (Blueprint $table) {
            $table->dropForeign(['level_id']);
            $table->dropUnique('level_subjects_level_id_subject_id_unique');
        });

        Schema::table('level_subjects', function (Blueprint $table) {
            $table->foreign('level_id')->references('id')->on('levels')->onDelete('cascade');
            $table->unique(['tenant_id', 'level_id', 'subject_id'], 'level_subjects_tenant_unique');
        });
    }

    public function down(): void
    {
        Schema::table('level_subjects', function (Blueprint $table) {
            $table->dropUnique('level_subjects_tenant_unique');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
            $table->unique(['level_id', 'subject_id']);
        });
    }
};
