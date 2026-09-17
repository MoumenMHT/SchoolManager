<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('data');
            $table->timestamp('last_activation_at')->nullable()->after('is_active');
            $table->timestamp('last_deactivation_at')->nullable()->after('last_activation_at');
            $table->string('subscription_plan')->default('basic')->after('last_deactivation_at');
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_plan');
            $table->string('contact_name')->nullable()->after('subscription_expires_at');
            $table->string('contact_email')->nullable()->after('contact_name');
            $table->string('contact_phone')->nullable()->after('contact_email');
            $table->integer('max_students')->default(0)->after('contact_phone');
            $table->text('notes')->nullable()->after('max_students');

            $table->index('is_active');
            $table->index('subscription_plan');
            $table->index('subscription_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['subscription_plan']);
            $table->dropIndex(['subscription_expires_at']);

            $table->dropColumn([
                'is_active',
                'last_activation_at',
                'last_deactivation_at',
                'subscription_plan',
                'subscription_expires_at',
                'contact_name',
                'contact_email',
                'contact_phone',
                'max_students',
                'notes',
            ]);
        });
    }
};
