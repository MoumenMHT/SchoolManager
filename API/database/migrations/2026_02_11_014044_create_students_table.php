<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('code')->unique();
            $table->date('birth_date');
            $table->enum('gender', ['male', 'female']);
            $table->foreignId('class_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('parent_id')->constrained()->onDelete('cascade');
            $table->date('enrollment_date');
            $table->text('medical_info')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
