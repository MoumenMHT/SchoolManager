<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::table('teachers', function (Blueprint $table) {
            try { $table->dropUnique(['cin']); } catch (\Exception $e) {}
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->unique(['tenant_id', 'cin'], 'teachers_tenant_cin_unique');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropUnique('teachers_tenant_cin_unique');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
            $table->unique('cin');
        });
    }
};
