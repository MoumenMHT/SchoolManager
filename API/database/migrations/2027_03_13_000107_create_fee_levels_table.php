<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_id')->constrained('fees')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['fee_id', 'level_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_levels');
    }
};
