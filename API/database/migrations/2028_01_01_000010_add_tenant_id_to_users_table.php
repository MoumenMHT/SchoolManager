<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Drop old global unique constraints
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        // Add new composite unique constraints scoped per tenant
        Schema::table('users', function (Blueprint $table) {
            $table->unique(['tenant_id', 'username'], 'users_tenant_username_unique');
            $table->unique(['tenant_id', 'email'], 'users_tenant_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_tenant_username_unique');
            $table->dropUnique('users_tenant_email_unique');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');

            // Restore original unique constraints
            $table->unique('username');
            $table->unique('email');
        });
    }
};
