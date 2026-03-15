<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->enum('cycle', ['primary', 'cem', 'lycee']);
            $table->unsignedTinyInteger('year_number');
            $table->string('track')->nullable();
            $table->string('name');
            $table->integer('sort_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['cycle', 'year_number', 'track']);
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('levels');
    }
};
