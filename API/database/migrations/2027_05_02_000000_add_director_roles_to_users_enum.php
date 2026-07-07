<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'teacher', 'parent', 'supervisor', 'secretariat', 'accountant', 'primary_director', 'cem_director', 'lycee_director') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'teacher', 'parent', 'supervisor', 'secretariat', 'accountant') NOT NULL");
        }
    }
};
