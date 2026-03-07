<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('parents')->onDelete('cascade');
            $table->foreignId('old_contract_id')->nullable()->constrained('contracts')->onDelete('cascade');
            $table->string('contract_number')->unique();
            $table->string('academic_year', 60);
            $table->decimal('total_fees', 10, 2);
            $table->string('discount_type', 60)->nullable();
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->text('discount_reason')->nullable();
            $table->decimal('monthly_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('remaining_amount', 10, 2);
            $table->decimal('balance', 10, 2)->default(0); // For overpayments/credits
            $table->date('start_date');
            $table->date('end_date');
            $table->text('notes')->nullable();
            $table->string('status', 50)->default('active'); // active, completed, cancelled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
