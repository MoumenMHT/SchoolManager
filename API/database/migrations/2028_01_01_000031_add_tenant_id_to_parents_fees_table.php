<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parents_fees', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::table('parents_fees', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropUnique('parents_fees_parent_id_student_id_fee_id_unique');
        });

        Schema::table('parents_fees', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('parents')->onDelete('cascade');
            $table->unique(['tenant_id', 'parent_id', 'student_id', 'fee_id'], 'parents_fees_tenant_unique');
        });
    }

    public function down(): void
    {
        Schema::table('parents_fees', function (Blueprint $table) {
            $table->dropUnique('parents_fees_tenant_unique');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
            $table->unique(['parent_id', 'fee_id']);
        });
    }
};
