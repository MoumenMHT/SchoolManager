<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_exam', function (Blueprint $table) {
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->primary(['exam_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_exam');
    }
};
