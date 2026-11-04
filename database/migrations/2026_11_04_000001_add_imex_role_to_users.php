<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin','warehouse','ppic','ie','prod','accountant','development','qa_qc','imex') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::table('users')->where('role', 'imex')->exists()) {
            throw new RuntimeException('Reassign IMEX accounts before rolling back the IMEX role migration.');
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin','warehouse','ppic','ie','prod','accountant','development','qa_qc') NOT NULL");
        }
    }
};
