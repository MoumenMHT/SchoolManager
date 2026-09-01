<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::table('contracts', function (Blueprint $table) {
            try { $table->dropUnique(['contract_number']); } catch (\Exception $e) {}
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->unique(['tenant_id', 'contract_number'], 'contracts_tenant_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropUnique('contracts_tenant_number_unique');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
            $table->unique('contract_number');
        });
    }
};
