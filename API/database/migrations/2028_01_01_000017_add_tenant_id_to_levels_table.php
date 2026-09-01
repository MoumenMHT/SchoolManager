<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Drop the old unique constraint: unique(['cycle', 'year_number', 'track'])
        Schema::table('levels', function (Blueprint $table) {
            try { $table->dropUnique(['cycle', 'year_number', 'track']); } catch (\Exception $e) {}
        });

        // Add new composite unique constraint scoped per tenant
        Schema::table('levels', function (Blueprint $table) {
            $table->unique(['tenant_id', 'cycle', 'year_number', 'track'], 'levels_tenant_unique');
        });
    }

    public function down(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->dropUnique('levels_tenant_unique');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
            $table->unique(['cycle', 'year_number', 'track']);
        });
    }
};
