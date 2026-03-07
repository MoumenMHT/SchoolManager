<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parents_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('parents')->onDelete('cascade');
            $table->foreignId('fee_id')->constrained('fees')->onDelete('cascade');
            $table->timestamps();
            
            // Prevent duplicate fee assignments
            $table->unique(['parent_id', 'fee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parents_fees');
    }
};
