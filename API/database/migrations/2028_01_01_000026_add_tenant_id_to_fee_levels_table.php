<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_levels', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::table('fee_levels', function (Blueprint $table) {
            $table->dropForeign(['fee_id']);
            $table->dropUnique('fee_levels_fee_id_level_id_unique');
        });

        Schema::table('fee_levels', function (Blueprint $table) {
            $table->foreign('fee_id')->references('id')->on('fees')->onDelete('cascade');
            $table->unique(['tenant_id', 'fee_id', 'level_id'], 'fee_levels_tenant_unique');
        });
    }

    public function down(): void
    {
        Schema::table('fee_levels', function (Blueprint $table) {
            $table->dropUnique('fee_levels_tenant_unique');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
            $table->unique(['fee_id', 'level_id']);
        });
    }
};
