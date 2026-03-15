<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->enum('day', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->unique(['teacher_id', 'day', 'start_time', 'end_time'], 'teacher_availability_unique_slot');
            $table->index(['teacher_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_availabilities');
    }
};
